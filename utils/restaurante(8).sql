-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 29-08-2025 a las 03:07:59
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `restaurante`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_archivar_transaccion` (IN `p_venta_id` INT, IN `p_destino_schema` VARCHAR(64))   BEGIN
  DECLARE v_dst VARCHAR(64) DEFAULT IFNULL(p_destino_schema,'restaurante_espejo');

  -- Evitar efectos secundarios de triggers (ver sección 3)
  SET @ARCHIVE_MODE = 1;

  START TRANSACTION;

  -- Preparar sets de IDs relacionados
  DROP TEMPORARY TABLE IF EXISTS tmp_detalles;
  CREATE TEMPORARY TABLE tmp_detalles (PRIMARY KEY(id)) AS
    SELECT id FROM restaurante.venta_detalles WHERE venta_id = p_venta_id;

  DROP TEMPORARY TABLE IF EXISTS tmp_tickets;
  CREATE TEMPORARY TABLE tmp_tickets (PRIMARY KEY(id)) AS
    SELECT id FROM restaurante.tickets WHERE venta_id = p_venta_id;

  /* ======================= COPIAS AL ESPEJO ======================= */

  -- Venta
  SET @sql = CONCAT('INSERT IGNORE INTO ', v_dst, '.ventas SELECT * FROM restaurante.ventas WHERE id=?');
  PREPARE s1 FROM @sql; SET @p := p_venta_id; EXECUTE s1 USING @p; DEALLOCATE PREPARE s1;

  -- Detalles (sin subtotal: columna generada)
  SET @sql = CONCAT(
    'INSERT IGNORE INTO ', v_dst, '.venta_detalles ',
    '(id, venta_id, producto_id, cantidad, precio_unitario, insumos_descargados, created_at, entregado_hr, estado_producto, observaciones) ',
    'SELECT id, venta_id, producto_id, cantidad, precio_unitario, insumos_descargados, created_at, entregado_hr, estado_producto, observaciones ',
    'FROM restaurante.venta_detalles WHERE venta_id=?'
  );
  PREPARE s2 FROM @sql; SET @p := p_venta_id; EXECUTE s2 USING @p; DEALLOCATE PREPARE s2;

  -- Log de estados por detalle
  SET @sql = CONCAT(
    'INSERT IGNORE INTO ', v_dst, '.venta_detalles_log ',
    'SELECT l.* FROM restaurante.venta_detalles_log l JOIN tmp_detalles d ON d.id=l.venta_detalle_id'
  );
  PREPARE s3 FROM @sql; EXECUTE s3; DEALLOCATE PREPARE s3;

  -- Respaldo de detalles cancelados que pertenezcan a la venta
  SET @sql = CONCAT(
    'INSERT IGNORE INTO ', v_dst, '.venta_detalles_cancelados ',
    'SELECT * FROM restaurante.venta_detalles_cancelados WHERE venta_id=?'
  );
  PREPARE s4 FROM @sql; SET @p := p_venta_id; EXECUTE s4 USING @p; DEALLOCATE PREPARE s4;

  -- Tickets de la venta
  SET @sql = CONCAT('INSERT IGNORE INTO ', v_dst, '.tickets SELECT * FROM restaurante.tickets WHERE venta_id=?');
  PREPARE s5 FROM @sql; SET @p := p_venta_id; EXECUTE s5 USING @p; DEALLOCATE PREPARE s5;

  -- Detalles de ticket (sin subtotal: columna generada)
  SET @sql = CONCAT(
    'INSERT IGNORE INTO ', v_dst, '.ticket_detalles ',
    '(id, ticket_id, producto_id, cantidad, precio_unitario) ',
    'SELECT id, ticket_id, producto_id, cantidad, precio_unitario ',
    'FROM restaurante.ticket_detalles WHERE ticket_id IN (SELECT id FROM tmp_tickets)'
  );
  PREPARE s6 FROM @sql; EXECUTE s6; DEALLOCATE PREPARE s6;

  -- Log de cancelaciones (venta o cualquiera de sus detalles)
  SET @sql = CONCAT(
    'INSERT IGNORE INTO ', v_dst, '.log_cancelaciones ',
    'SELECT lc.* FROM restaurante.log_cancelaciones lc ',
    'LEFT JOIN tmp_detalles d ON lc.venta_detalle_id = d.id ',
    'WHERE lc.venta_id = ? OR d.id IS NOT NULL'
  );
  PREPARE s7 FROM @sql; SET @p := p_venta_id; EXECUTE s7 USING @p; DEALLOCATE PREPARE s7;

  
  /* ======================= BORRADO EN OPERATIVO ======================= */

  -- Logs de cancelaciones (los asociados a la venta o a sus detalles)
  DELETE lc FROM restaurante.log_cancelaciones lc
  LEFT JOIN tmp_detalles d ON lc.venta_detalle_id = d.id
  WHERE lc.venta_id = p_venta_id OR d.id IS NOT NULL;

  -- Logs por detalle
  DELETE l FROM restaurante.venta_detalles_log l JOIN tmp_detalles d ON d.id = l.venta_detalle_id;

  -- Respaldo de detalles cancelados de esa venta
  DELETE dc FROM restaurante.venta_detalles_cancelados dc WHERE dc.venta_id = p_venta_id;

  -- Detalles de ticket
  DELETE td FROM restaurante.ticket_detalles td WHERE td.ticket_id IN (SELECT id FROM tmp_tickets);

  -- Tickets
  DELETE t FROM restaurante.tickets t WHERE t.venta_id = p_venta_id;

  -- Detalles de venta
  DELETE d FROM restaurante.venta_detalles d WHERE d.venta_id = p_venta_id;

  -- Venta
  DELETE v FROM restaurante.ventas v WHERE v.id = p_venta_id;

  COMMIT;

  -- Limpieza
  SET @ARCHIVE_MODE = NULL;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cerrar_corte` (IN `p_usuario_id` INT)   BEGIN
    DECLARE v_corte_id INT;
    DECLARE total_ventas DECIMAL(10,2);

    SELECT id INTO v_corte_id 
    FROM corte_caja 
    WHERE usuario_id = p_usuario_id AND fecha_fin IS NULL 
    LIMIT 1;

    SELECT SUM(total) INTO total_ventas 
    FROM ventas
    WHERE usuario_id = p_usuario_id AND estatus = 'cerrada' AND corte_id IS NULL;


    UPDATE corte_caja
    SET fecha_fin = NOW(), total = total_ventas
    WHERE id = v_corte_id;

    UPDATE ventas
    SET corte_id = v_corte_id
    WHERE usuario_id = p_usuario_id 
      AND estatus = 'cerrada'
      AND corte_id IS NULL;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalcular_productos_por_insumo` (IN `p_insumo_id` INT)   BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_producto_id INT;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT r.producto_id
        FROM recetas r
        WHERE r.insumo_id = p_insumo_id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_producto_id;
        IF done THEN
            LEAVE read_loop;
        END IF;

        UPDATE productos p
        SET existencia = (
            SELECT IFNULL(MIN(FLOOR(i.existencia / r.cantidad)), 0)
            FROM recetas r
            JOIN insumos i ON r.insumo_id = i.id
            WHERE r.producto_id = v_producto_id
        )
        WHERE p.id = v_producto_id;

    END LOOP;
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_recalcular_todos` ()   BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_insumo_id INT;
    DECLARE cur CURSOR FOR
        SELECT DISTINCT r.insumo_id
        FROM recetas r;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    rec_loop: LOOP
        FETCH cur INTO v_insumo_id;
        IF done THEN LEAVE rec_loop; END IF;
        CALL sp_recalcular_productos_por_insumo(v_insumo_id);
    END LOOP;
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alineacion`
--

CREATE TABLE `alineacion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alineacion`
--

INSERT INTO `alineacion` (`id`, `nombre`) VALUES
(3, 'Ala Derecha'),
(4, 'Cocina');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_areas`
--

CREATE TABLE `catalogo_areas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_areas`
--

INSERT INTO `catalogo_areas` (`id`, `nombre`) VALUES
(1, 'Ala izquierda'),
(2, 'Ala derecha');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_bancos`
--

CREATE TABLE `catalogo_bancos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_bancos`
--

INSERT INTO `catalogo_bancos` (`id`, `nombre`) VALUES
(1, 'BBVA'),
(2, 'Santander'),
(3, 'Banorte'),
(4, 'HSBC'),
(5, 'Scotiabank'),
(6, 'Banco Azteca'),
(7, 'Inbursa'),
(8, 'BanCoppel');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_categorias`
--

CREATE TABLE `catalogo_categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_categorias`
--

INSERT INTO `catalogo_categorias` (`id`, `nombre`) VALUES
(1, 'Bebida'),
(2, 'Postre'),
(3, 'Platillo'),
(4, 'Sopa'),
(5, 'Arroz'),
(6, 'Extra'),
(7, 'Topping'),
(8, 'Rollo natural'),
(9, 'Rollo empanizado'),
(10, 'Entrada'),
(11, 'Rollo Premium'),
(12, 'Rollo Nano');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_denominaciones`
--

CREATE TABLE `catalogo_denominaciones` (
  `id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `descripcion` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_denominaciones`
--

INSERT INTO `catalogo_denominaciones` (`id`, `valor`, `descripcion`) VALUES
(1, 0.50, 'Moneda 50 centavos'),
(2, 1.00, 'Moneda 1 peso'),
(3, 2.00, 'Moneda 2 pesos'),
(4, 5.00, 'Moneda 5 pesos'),
(5, 10.00, 'Moneda 10 pesos'),
(6, 20.00, 'Moneda 20 pesos'),
(7, 50.00, 'Billete 50 pesos'),
(8, 100.00, 'Billete 100 pesos'),
(9, 200.00, 'Billete 200 pesos'),
(10, 500.00, 'Billete 500 pesos'),
(11, 1000.00, 'Billete 1000 pesos'),
(12, 1.00, 'Pago Boucher'),
(13, 1.00, 'Pago Cheque');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_folios`
--

CREATE TABLE `catalogo_folios` (
  `id` int(11) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `folio_actual` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_folios`
--

INSERT INTO `catalogo_folios` (`id`, `descripcion`, `folio_actual`) VALUES
(1, 'Serie Restaurante', 1000),
(2, 'Serie Domicilio', 2019);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_tarjetas`
--

CREATE TABLE `catalogo_tarjetas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_tarjetas`
--

INSERT INTO `catalogo_tarjetas` (`id`, `nombre`) VALUES
(1, 'Visa'),
(2, 'MasterCard');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes_facturacion`
--

CREATE TABLE `clientes_facturacion` (
  `id` int(11) NOT NULL,
  `rfc` varchar(20) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `correo` varchar(150) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `calle` varchar(150) DEFAULT NULL,
  `numero_ext` varchar(20) DEFAULT NULL,
  `numero_int` varchar(20) DEFAULT NULL,
  `colonia` varchar(120) DEFAULT NULL,
  `municipio` varchar(120) DEFAULT NULL,
  `estado` varchar(120) DEFAULT NULL,
  `pais` varchar(100) DEFAULT 'México',
  `cp` varchar(10) DEFAULT NULL,
  `regimen` varchar(100) DEFAULT NULL,
  `uso_cfdi` varchar(10) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes_facturacion`
--

INSERT INTO `clientes_facturacion` (`id`, `rfc`, `razon_social`, `correo`, `telefono`, `calle`, `numero_ext`, `numero_int`, `colonia`, `municipio`, `estado`, `pais`, `cp`, `regimen`, `uso_cfdi`, `created_at`, `updated_at`) VALUES
(1, 'marf9401109i5', 'fued majul', 'fu@co.com', '123456789', 'prueba', '123', '', 'analco', 'durango', 'urango', 'México', '34000', 'persona moral', 'g01', '2025-08-28 19:04:50', '2025-08-28 19:04:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `corte_caja`
--

CREATE TABLE `corte_caja` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `folio_inicio` int(11) DEFAULT NULL,
  `folio_fin` int(11) DEFAULT NULL,
  `total_folios` int(11) NOT NULL DEFAULT 0,
  `fecha_fin` datetime DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fondo_inicial` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `corte_caja`
--

INSERT INTO `corte_caja` (`id`, `usuario_id`, `fecha_inicio`, `folio_inicio`, `folio_fin`, `total_folios`, `fecha_fin`, `total`, `observaciones`, `fondo_inicial`) VALUES
(58, 1, '2025-08-27 19:11:28', 2010, 2013, 3, '2025-08-27 19:01:33', 500.00, '', 500.00),
(59, 1, '2025-08-28 03:14:02', 2013, 2016, 3, '2025-08-27 19:17:23', 4000.00, '', 4000.00),
(60, 1, '2025-08-28 03:25:15', 2016, 2017, 1, '2025-08-27 19:27:33', 4000.00, '', 4000.00),
(61, 1, '2025-08-28 03:29:49', 2017, 2019, 2, '2025-08-28 18:54:48', 6000.00, '', 6000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `corte_caja_historial`
--

CREATE TABLE `corte_caja_historial` (
  `id` int(11) NOT NULL,
  `corte_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `datos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_json`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `corte_caja_historial`
--

INSERT INTO `corte_caja_historial` (`id`, `corte_id`, `usuario_id`, `fecha`, `total`, `observaciones`, `datos_json`) VALUES
(11, 58, 1, '2025-08-27 19:01:06', 6701.00, '', '{\"efectivo\":{\"productos\":476,\"propina\":0,\"total\":476},\"boucher\":{\"productos\":725,\"propina\":0,\"total\":725},\"total_productos\":1201,\"total_propinas\":0,\"totalEsperado\":1201,\"fondo\":500,\"total_depositos\":5000,\"total_retiros\":0,\"totalFinal\":6701,\"corte_id\":58,\"total_meseros\":[{\"nombre\":\"alinne Guadalupe Gurrola ramirez\",\"total\":0},{\"nombre\":\"gilberto ozuna carrillo\",\"total\":476},{\"nombre\":\"Javier Emanuel lopez lozano\",\"total\":725},{\"nombre\":\"Jesus\",\"total\":0},{\"nombre\":\"juan hernesto ortega Almanza\",\"total\":0},{\"nombre\":\"Mesas general\",\"total\":0}],\"total_rapido\":1201,\"total_repartidor\":[{\"nombre\":\"Didi\",\"total\":0},{\"nombre\":\"Rappi\",\"total\":0},{\"nombre\":\"Repartidor casa\",\"total\":0},{\"nombre\":\"Uber\",\"total\":0}],\"fecha_inicio\":\"2025-08-27 19:11:28\",\"folio_inicio\":2012,\"folio_fin\":2013,\"total_folios\":2}');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `desglose_corte`
--

CREATE TABLE `desglose_corte` (
  `id` int(11) NOT NULL,
  `corte_id` int(11) NOT NULL,
  `denominacion` decimal(10,2) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `tipo_pago` enum('efectivo','boucher','cheque') DEFAULT 'efectivo',
  `denominacion_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `desglose_corte`
--

INSERT INTO `desglose_corte` (`id`, `corte_id`, `denominacion`, `cantidad`, `tipo_pago`, `denominacion_id`) VALUES
(232, 58, 1.00, 725, 'boucher', 12),
(233, 58, 1.00, 1, 'efectivo', 2),
(234, 58, 200.00, 1, 'efectivo', 9),
(235, 58, 500.00, 1, 'efectivo', 10),
(236, 58, 1000.00, 6, 'efectivo', 11),
(237, 58, 725.00, 1, 'boucher', NULL),
(238, 59, 1.00, 115, 'efectivo', 12),
(239, 59, 1.00, 145, 'efectivo', 13),
(240, 59, 2.00, 2, 'efectivo', 3),
(241, 59, 5.00, 1, 'efectivo', 4),
(242, 59, 20.00, 2, 'efectivo', 6),
(243, 59, 50.00, 1, 'efectivo', 7),
(244, 59, 1000.00, 4, 'efectivo', 11),
(245, 59, 115.00, 1, 'boucher', NULL),
(246, 59, 145.00, 1, 'cheque', NULL),
(247, 60, 1.00, 49, 'boucher', 12),
(248, 60, 1000.00, 4, 'efectivo', 11),
(249, 60, 49.00, 1, 'boucher', NULL),
(250, 61, 1000.00, 6, 'efectivo', 11),
(251, 61, 10.00, 1, 'boucher', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas_detalle`
--

CREATE TABLE `entradas_detalle` (
  `id` int(11) NOT NULL,
  `entrada_id` int(11) DEFAULT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas_insumo`
--

CREATE TABLE `entradas_insumo` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

CREATE TABLE `facturas` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `folio` varchar(50) DEFAULT NULL,
  `uuid` varchar(64) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_emision` datetime DEFAULT current_timestamp(),
  `estado` enum('generada','cancelada') DEFAULT 'generada',
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_detalles`
--

CREATE TABLE `factura_detalles` (
  `id` int(11) NOT NULL,
  `factura_id` int(11) NOT NULL,
  `ticket_detalle_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `importe` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fondo`
--

CREATE TABLE `fondo` (
  `usuario_id` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `fondo`
--

INSERT INTO `fondo` (`usuario_id`, `monto`) VALUES
(1, 6000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `id` int(11) NOT NULL,
  `dia_semana` varchar(15) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `serie_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `horarios`
--

INSERT INTO `horarios` (`id`, `dia_semana`, `hora_inicio`, `hora_fin`, `serie_id`) VALUES
(1, 'Sabado', '00:00:00', '23:59:59', 2),
(2, 'Lunes', '00:00:00', '23:59:00', 2),
(3, 'Viernes', '00:00:00', '23:59:00', 2),
(4, 'martes', '00:00:00', '23:59:59', 2),
(5, 'miercoles', '00:00:00', '23:59:59', 2),
(6, 'jueves', '00:00:00', '23:59:59', 2),
(7, 'domingo', '00:00:00', '23:59:59', 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `unidad` varchar(20) DEFAULT NULL,
  `existencia` decimal(10,2) DEFAULT NULL,
  `tipo_control` enum('por_receta','unidad_completa','uso_general','no_controlado','desempaquetado') DEFAULT 'por_receta',
  `imagen` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `insumos`
--

INSERT INTO `insumos` (`id`, `nombre`, `unidad`, `existencia`, `tipo_control`, `imagen`) VALUES
(1, 'Arroz', 'gramos', 1140.00, 'por_receta', 'ins_68717301313ad.jpg'),
(2, 'Alga', 'piezas', 2995.00, 'por_receta', 'ins_6871716a72681.jpg'),
(3, 'Salmón fresco', 'gramos', 3000.00, 'por_receta', 'ins_6871777fa2c56.png'),
(4, 'Refresco en lata', 'piezas', 2987.00, 'unidad_completa', 'ins_6871731d075cb.webp'),
(7, 'Surimi', 'gramos', 3000.00, 'uso_general', 'ins_688a521dcd583.jpg'),
(8, 'Tocino', 'gramos', 2970.00, 'uso_general', 'ins_688a4dc84c002.jpg'),
(9, 'Pollo', 'gramos', 2965.00, 'desempaquetado', 'ins_688a4e4bd5999.jpg'),
(10, 'Camarón', 'gramos', 2925.00, 'desempaquetado', 'ins_688a4f5c873c6.jpg'),
(11, 'Queso Chihuahua', 'gramos', 3000.00, 'unidad_completa', 'ins_688a4feca9865.jpg'),
(12, 'Philadelphia', 'gramos', 2740.00, 'uso_general', 'ins_688a504f9cb40.jpg'),
(13, 'Arroz blanco', 'gramos', 2990.00, 'por_receta', 'ins_689f82d674c65.jpg'),
(14, 'Carne', 'gramos', 2690.00, 'uso_general', 'ins_688a528d1261a.jpg'),
(15, 'Queso Amarillo', 'piezas', 3000.00, 'uso_general', 'ins_688a53246c1c2.jpg'),
(16, 'Ajonjolí', 'gramos', 2978.00, 'uso_general', 'ins_689f824a23343.jpg'),
(17, 'Panko', 'gramos', 3000.00, 'por_receta', 'ins_688a53da64b5f.jpg'),
(18, 'Salsa tampico', 'mililitros', 30000.00, 'no_controlado', 'ins_688a54cf1872b.jpg'),
(19, 'Anguila', 'oz', 2990.00, 'por_receta', 'ins_689f828638aa9.jpg'),
(20, 'BBQ', 'oz', 3000.00, 'no_controlado', 'ins_688a557431fce.jpg'),
(21, 'Serrano', 'gramos', 3000.00, 'uso_general', 'ins_688a55c66f09d.jpg'),
(22, 'Chile Morrón', 'gramos', 3000.00, 'por_receta', 'ins_688a5616e8f25.jpg'),
(23, 'Kanikama', 'gramos', 3000.00, 'por_receta', 'ins_688a5669e24a8.jpg'),
(24, 'Aguacate', 'gramos', 2715.00, 'por_receta', 'ins_689f8254c2e71.jpg'),
(25, 'Dedos de queso', 'pieza', 3000.00, 'unidad_completa', 'ins_688a56fda3221.jpg'),
(26, 'Mango', 'gramos', 2970.00, 'por_receta', 'ins_688a573c762f4.jpg'),
(27, 'Tostadas', 'pieza', 3000.00, 'uso_general', 'ins_688a57a499b35.jpg'),
(28, 'Papa', 'gramos', 3000.00, 'por_receta', 'ins_688a580061ffd.jpg'),
(29, 'Cebolla Morada', 'gramos', 3000.00, 'por_receta', 'ins_688a5858752a0.jpg'),
(30, 'Salsa de soya', 'mililitros', 30000.00, 'no_controlado', 'ins_688a58cc6cb6c.jpg'),
(31, 'Naranja', 'gramos', 3000.00, 'por_receta', 'ins_688a590bca275.jpg'),
(32, 'Chile Caribe', 'gramos', 2985.00, 'por_receta', 'ins_688a59836c32e.jpg'),
(33, 'Pulpo', 'gramos', 2970.00, 'por_receta', 'ins_688a59c9a1d0b.jpg'),
(34, 'Zanahoria', 'gramos', 3000.00, 'por_receta', 'ins_688a5a0a3a959.jpg'),
(35, 'Apio', 'gramos', 2990.00, 'por_receta', 'ins_688a5a52af990.jpg'),
(36, 'Pepino', 'gramos', 2690.00, 'uso_general', 'ins_688a5aa0cbaf5.jpg'),
(37, 'Masago', 'gramos', 3000.00, 'por_receta', 'ins_688a5b3f0dca6.jpg'),
(38, 'Nuez de la india', 'gramos', 2990.00, 'por_receta', 'ins_688a5be531e11.jpg'),
(39, 'Cátsup', 'mililitros', 2990.00, 'por_receta', 'ins_688a5c657eb83.jpg'),
(40, 'Atún fresco', 'gramos', 2970.00, 'por_receta', 'ins_688a5ce18adc5.jpg'),
(41, 'Callo almeja', 'gramos', 2970.00, 'por_receta', 'ins_688a5d28de8a5.jpg'),
(42, 'Calabacin', 'gramos', 3000.00, 'por_receta', 'ins_688a5d6b2bca1.jpg'),
(43, 'Fideo chino transparente', 'gramos', 3000.00, 'por_receta', 'ins_688a5dd3b406d.jpg'),
(44, 'Brócoli', 'gramos', 3000.00, 'por_receta', 'ins_688a5e2736870.jpg'),
(45, 'Chile de árbol', 'gramos', 2990.00, 'por_receta', 'ins_688a5e6f08ccd.jpg'),
(46, 'Pasta udon', 'gramos', 3000.00, 'por_receta', 'ins_688a5eb627f38.jpg'),
(47, 'Huevo', 'pieza', 3000.00, 'por_receta', 'ins_688a5ef9b575e.jpg'),
(48, 'Cerdo', 'gramos', 3000.00, 'por_receta', 'ins_688a5f3915f5e.jpg'),
(49, 'Masa para gyozas', 'pieza', 3000.00, 'por_receta', 'ins_688a5fae2e7f1.jpg'),
(50, 'Naruto', 'gramos', 3000.00, 'por_receta', 'ins_688a5ff57f62d.jpg'),
(51, 'Atún ahumado', 'gramos', 3000.00, 'por_receta', 'ins_68adcd62c5a19.jpg'),
(52, 'Cacahuate con salsa (salado)', 'gramos', 3000.00, 'por_receta', 'ins_68adcf253bd1d.jpg'),
(53, 'Calabaza', 'gramos', 3000.00, 'por_receta', 'ins_68add0ff781fb.jpg'),
(54, 'Camarón gigante para pelar', 'pieza', 3000.00, 'por_receta', 'ins_68add3264c465.jpg'),
(55, 'Cebolla', 'gramos', 2989.80, 'por_receta', 'ins_68add38beff59.jpg'),
(56, 'Chile en polvo', 'gramos', 3000.00, 'por_receta', 'ins_68add4a750a0e.jpg'),
(57, 'Coliflor', 'gramos', 3000.00, 'por_receta', 'ins_68add5291130e.jpg'),
(59, 'Dedos de surimi', 'pieza', 3000.00, 'unidad_completa', 'ins_68add5c575fbb.jpg'),
(60, 'Fideos', 'gramos', 3000.00, 'por_receta', 'ins_68add629d094b.jpg'),
(61, 'Fondo de res', 'mililitros', 30000.00, 'no_controlado', 'ins_68add68d317d5.jpg'),
(62, 'Gravy Naranja', 'oz', 3000.00, 'no_controlado', 'ins_68add7bb461b3.jpg'),
(63, 'Salsa Aguachil', 'oz', 2996.00, 'no_controlado', 'ins_68ae000034b31.jpg'),
(64, 'Julianas de zanahoria', 'gramos', 2990.00, 'por_receta', 'ins_68add82c9c245.jpg'),
(65, 'Limón', 'gramos', 3000.00, 'por_receta', 'ins_68add890ee640.jpg'),
(66, 'Queso Mix', 'gramos', 2600.00, 'uso_general', 'ins_68ade1625f489.jpg'),
(67, 'Morrón', 'gramos', 3000.00, 'por_receta', 'ins_68addcbc6d15a.jpg'),
(69, 'Pasta chukasoba', 'gramos', 3000.00, 'por_receta', 'ins_68addd277fde6.jpg'),
(70, 'Pasta frita', 'gramos', 3000.00, 'por_receta', 'ins_68addd91a005e.jpg'),
(71, 'Queso crema', 'gramos', 3000.00, 'uso_general', 'ins_68ade11cdadcb.jpg'),
(72, 'Refresco embotellado', 'pieza', 2990.00, 'unidad_completa', 'ins_68adfdd53f04e.jpg'),
(73, 'res', 'gramos', 3000.00, 'uso_general', 'ins_68adfe2e49580.jpg'),
(74, 'Rodajas de naranja', 'gramos', 3000.00, 'por_receta', 'ins_68adfeccd68d8.jpg'),
(75, 'Salmón', 'gramos', 3000.00, 'por_receta', 'ins_68adffa2a2db0.jpg'),
(76, 'Salsa de anguila', 'mililitros', 30000.00, 'no_controlado', 'ins_68ae005f1b3cd.jpg'),
(77, 'Salsa teriyaki (dulce)', 'mililitros', 30000.00, 'no_controlado', 'ins_68ae00c53121a.jpg'),
(78, 'Salsas orientales', 'mililitros', 30000.00, 'no_controlado', 'ins_68ae01341e7b1.jpg'),
(79, 'Shisimi', 'gramos', 3000.00, 'uso_general', 'ins_68ae018d22a63.jpg'),
(80, 'Siracha', 'mililitros', 2930.00, 'no_controlado', 'ins_68ae03413da26.jpg'),
(81, 'Tampico', 'mililitros', 2990.00, 'uso_general', 'ins_68ae03f65bd71.jpg'),
(82, 'Tortilla de harina', 'pieza', 3000.00, 'unidad_completa', 'ins_68ae04b46d24a.jpg'),
(83, 'Tostada', 'pieza', 3000.00, 'unidad_completa', 'ins_68ae05924a02a.jpg'),
(85, 'Yakimeshi mini', 'gramos', 3000.00, 'por_receta', 'ins_68ae061b1175b.jpg'),
(86, 'Sal con Ajo', 'pieza', 3000.00, 'por_receta', 'ins_68adff6dbf111.jpg'),
(87, 'Aderezo Chipotle', 'mililitros', 2740.00, 'por_receta', 'ins_68adcabeb1ee9.jpg'),
(88, 'Mezcla de Horneado', 'gramos', 2920.00, 'por_receta', 'ins_68addaa3e53f7.jpg'),
(89, 'Aderezo', 'gramos', 2880.00, 'uso_general', 'ins_68adcc0771a3c.jpg'),
(90, 'Camarón Empanizado', 'gramos', 2765.00, 'por_receta', 'ins_68add1de1aa0e.jpg'),
(91, 'Pollo Empanizado', 'gramos', 3000.00, 'por_receta', 'ins_68adde81c6be3.jpg'),
(92, 'Cebollín', 'gramos', 2995.00, 'por_receta', 'ins_68add3e38d04b.jpg'),
(93, 'Aderezo Cebolla Dul.', 'oz', 3000.00, 'uso_general', 'ins_68adcb8fa562e.jpg'),
(94, 'Camaron Enchiloso', 'gramos', 3000.00, 'por_receta', 'ins_68add2db69e2e.jpg'),
(95, 'Pastel chocoflan', 'pieza', 199.00, 'unidad_completa', 'ins_68adddfa22fe2.jpg'),
(96, 'Pay de queso', 'pieza', 199.00, 'unidad_completa', 'ins_68adde4fa8275.jpg'),
(97, 'Helado tempura', 'pieza', 200.00, 'unidad_completa', 'ins_68add7e53c6fe.jpg'),
(98, 'Postre especial', 'pieza', 199.00, 'unidad_completa', 'ins_68addee98fdf0.jpg'),
(99, 'Búfalo', 'mililitros', 3000.00, 'no_controlado', 'ins_68adce63dd347.jpg'),
(101, 'Corona 1/2', 'pieza', 300.00, 'unidad_completa', 'ins_68add55a1e3b7.jpg'),
(102, 'Golden Light 1/2', 'pieza', 300.00, 'unidad_completa', 'ins_68add76481f22.jpg'),
(103, 'Negra Modelo', 'pieza', 300.00, 'unidad_completa', 'ins_68addc59c2ea9.jpg'),
(104, 'Modelo Especial', 'pieza', 300.00, 'unidad_completa', 'ins_68addb9d59000.jpg'),
(105, 'Bud Light', 'pieza', 296.00, 'unidad_completa', 'ins_68adcdf3295e8.jpg'),
(106, 'Stella Artois', 'pieza', 300.00, 'unidad_completa', 'ins_68ae0397afb2f.jpg'),
(107, 'Ultra 1/2', 'pieza', 300.00, 'unidad_completa', 'ins_68ae05466a8e2.jpg'),
(108, 'Michelob 1/2', 'pieza', 300.00, 'unidad_completa', 'ins_68addb2d00c85.jpg'),
(109, 'Alitas de pollo', 'gramos', 3000.00, 'unidad_completa', 'ins_68adccf5a1147.jpg'),
(110, 'Ranch', 'mililitros', 3000.00, 'no_controlado', 'ins_68adfcddef7e3.jpg'),
(111, 'Buffalo', 'gramos', 3000.00, 'no_controlado', ''),
(112, 'Chichimi', 'gramos', 3000.00, 'no_controlado', 'ins_68add45bdb306.jpg'),
(113, 'Calpico', 'pieza', 300.00, 'unidad_completa', 'ins_68add19570673.jpg'),
(114, 'Vaina de soja', 'gramos', 3000.00, 'uso_general', 'ins_68ae05de869d1.jpg'),
(115, 'Boneless', 'gramos', 2000.00, 'por_receta', 'ins_68adcdbb6b5b4.jpg'),
(116, 'Agua members', 'pieza', 299.00, 'unidad_completa', 'ins_68adcc5feaee1.jpg'),
(117, 'Agua mineral', 'pieza', 3000.00, 'unidad_completa', 'ins_68adcca85ae2c.jpg'),
(118, 'Cilantro', 'gramos', 3000.00, 'por_receta', 'ins_68add4edab118.jpg'),
(119, 'Té de jazmin', 'mililitros', 29000.00, 'por_receta', 'ins_68ae0474dfc36.jpg');

--
-- Disparadores `insumos`
--
DELIMITER $$
CREATE TRIGGER `trg_update_insumo_existencia` AFTER UPDATE ON `insumos` FOR EACH ROW BEGIN
    IF NEW.existencia != OLD.existencia THEN
        CALL sp_recalcular_productos_por_insumo(NEW.id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_accion`
--

CREATE TABLE `logs_accion` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `accion` varchar(100) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `referencia_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `logs_accion`
--

INSERT INTO `logs_accion` (`id`, `usuario_id`, `modulo`, `accion`, `fecha`, `referencia_id`) VALUES
(646, 1, 'corte_caja', 'Creación de corte', '2025-08-27 11:11:28', 58),
(647, 6, 'ventas', 'Alta de venta', '2025-08-27 11:12:37', 120),
(648, 2, 'ventas', 'Alta de venta', '2025-08-27 11:33:50', 121),
(649, 6, 'ventas', 'Alta de venta', '2025-08-27 11:58:47', 122),
(650, 5, 'ventas', 'Alta de venta', '2025-08-27 11:59:12', 123),
(651, NULL, 'cocina', 'Producto iniciado', '2025-08-27 13:48:48', 234),
(652, NULL, 'cocina', 'Producto iniciado', '2025-08-27 13:48:49', 235),
(653, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 13:48:50', 235),
(654, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 13:48:54', 234),
(655, NULL, 'cocina', 'Producto iniciado', '2025-08-27 13:49:33', 236),
(656, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 15:58:17', 236),
(657, 5, 'ventas', 'Alta de venta', '2025-08-27 16:09:28', 124),
(658, 5, 'ventas', 'Alta de venta', '2025-08-27 18:55:53', 125),
(659, NULL, 'cocina', 'Producto iniciado', '2025-08-27 18:56:01', 240),
(660, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 18:56:02', 240),
(661, 2, 'ventas', 'Alta de venta', '2025-08-27 18:57:17', 126),
(662, NULL, 'cocina', 'Producto iniciado', '2025-08-27 18:57:22', 241),
(663, NULL, 'cocina', 'Producto iniciado', '2025-08-27 18:57:23', 242),
(664, NULL, 'cocina', 'Producto iniciado', '2025-08-27 18:57:24', 243),
(665, NULL, 'cocina', 'Producto iniciado', '2025-08-27 18:57:24', 244),
(666, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 18:57:25', 241),
(667, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 18:57:26', 242),
(668, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 18:57:26', 243),
(669, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 18:57:27', 244),
(670, 1, 'corte_caja', 'Cierre de corte', '2025-08-27 19:01:33', 58),
(671, 1, 'corte_caja', 'Creación de corte', '2025-08-27 19:14:02', 59),
(672, 4, 'ventas', 'Alta de venta', '2025-08-27 19:14:36', 127),
(673, NULL, 'cocina', 'Producto iniciado', '2025-08-27 19:14:42', 245),
(674, NULL, 'cocina', 'Producto iniciado', '2025-08-27 19:14:43', 246),
(675, NULL, 'cocina', 'Producto iniciado', '2025-08-27 19:14:44', 247),
(676, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 19:14:44', 245),
(677, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 19:14:48', 246),
(678, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 19:14:49', 247),
(679, 1, 'corte_caja', 'Cierre de corte', '2025-08-27 19:17:23', 59),
(680, 1, 'corte_caja', 'Creación de corte', '2025-08-27 19:25:15', 60),
(681, 4, 'ventas', 'Alta de venta', '2025-08-27 19:25:29', 128),
(682, NULL, 'cocina', 'Producto iniciado', '2025-08-27 19:26:20', 248),
(683, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 19:26:21', 248),
(684, 1, 'corte_caja', 'Cierre de corte', '2025-08-27 19:27:33', 60),
(685, 1, 'corte_caja', 'Creación de corte', '2025-08-27 19:29:49', 61),
(686, 5, 'ventas', 'Alta de venta', '2025-08-27 19:30:40', 129),
(687, NULL, 'cocina', 'Producto iniciado', '2025-08-27 19:30:45', 249),
(688, NULL, 'cocina', 'Producto marcado como listo', '2025-08-27 19:30:45', 249),
(689, 5, 'ventas', 'Alta de venta', '2025-08-28 16:59:24', 130),
(690, NULL, 'cocina', 'Producto iniciado', '2025-08-28 16:59:43', 250),
(691, NULL, 'cocina', 'Producto marcado como listo', '2025-08-28 16:59:46', 250),
(692, 1, 'corte_caja', 'Cierre de corte', '2025-08-28 18:54:48', 61);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_asignaciones_mesas`
--

CREATE TABLE `log_asignaciones_mesas` (
  `id` int(11) NOT NULL,
  `mesa_id` int(11) NOT NULL,
  `mesero_anterior_id` int(11) DEFAULT NULL,
  `mesero_nuevo_id` int(11) DEFAULT NULL,
  `fecha_cambio` datetime DEFAULT current_timestamp(),
  `usuario_que_asigna_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_cancelaciones`
--

CREATE TABLE `log_cancelaciones` (
  `id` int(11) NOT NULL,
  `tipo` enum('venta','detalle') NOT NULL,
  `venta_id` int(11) DEFAULT NULL,
  `venta_detalle_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `total_anterior` decimal(10,2) DEFAULT NULL,
  `subtotal_detalle` decimal(10,2) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `log_cancelaciones`
--

INSERT INTO `log_cancelaciones` (`id`, `tipo`, `venta_id`, `venta_detalle_id`, `usuario_id`, `motivo`, `total_anterior`, `subtotal_detalle`, `fecha`) VALUES
(1, 'detalle', 122, 233, NULL, 'Eliminación de producto', NULL, 155.00, '2025-08-27 11:59:33'),
(2, 'detalle', 122, 232, NULL, 'Eliminación de producto', NULL, 29.00, '2025-08-27 12:06:39'),
(3, 'venta', 122, NULL, 6, '', 0.00, NULL, '2025-08-27 12:06:39'),
(8, 'detalle', 124, 238, NULL, 'Eliminación de producto', NULL, 10.00, '2025-08-27 16:21:49'),
(9, 'detalle', 124, 239, NULL, 'Eliminación de producto', NULL, 35.00, '2025-08-27 16:21:49'),
(10, 'venta', 124, NULL, 5, '', 0.00, NULL, '2025-08-27 16:21:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_mesas`
--

CREATE TABLE `log_mesas` (
  `id` int(11) NOT NULL,
  `mesa_id` int(11) NOT NULL,
  `venta_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `menu_dia`
--

CREATE TABLE `menu_dia` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `menu_dia`
--

INSERT INTO `menu_dia` (`id`, `nombre`, `precio`, `fecha`) VALUES
(1, 'Enchiladas Verdes', 85.00, '2025-07-28'),
(2, 'Pozole', 95.00, '2025-07-28'),
(3, 'Tacos de Barbacoa', 90.00, '2025-07-28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas`
--

CREATE TABLE `mesas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `estado` enum('libre','ocupada','reservada') DEFAULT 'libre',
  `capacidad` int(11) DEFAULT 4,
  `mesa_principal_id` int(11) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `tiempo_ocupacion_inicio` datetime DEFAULT NULL,
  `estado_reserva` enum('ninguna','reservada') DEFAULT 'ninguna',
  `nombre_reserva` varchar(100) DEFAULT NULL,
  `fecha_reserva` datetime DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `ticket_enviado` tinyint(1) DEFAULT 0,
  `alineacion_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mesas`
--

INSERT INTO `mesas` (`id`, `nombre`, `estado`, `capacidad`, `mesa_principal_id`, `area`, `tiempo_ocupacion_inicio`, `estado_reserva`, `nombre_reserva`, `fecha_reserva`, `usuario_id`, `area_id`, `ticket_enviado`, `alineacion_id`) VALUES
(1, 'Mesa 1', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 6, 1, 0, NULL),
(2, 'Mesa 2', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 2, 2, 0, 3),
(3, 'Mesa 3', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 5, 1, 0, 4),
(4, 'Mesa 4', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 4, 1, 0, NULL),
(5, 'Mesa 5', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 17, 2, 0, NULL),
(6, 'Mesa 6', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 5, 1, 0, 3),
(7, 'Mesa 7', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 5, 1, 0, NULL),
(8, 'Mesa 8', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 6, 2, 0, NULL),
(9, 'Mesa 9', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(10, 'Mesa 10', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(11, 'Mesa 11', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 5, 2, 0, NULL),
(12, 'Mesa 12', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 5, 1, 0, 3),
(13, 'Mesa 13', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(14, 'Mesa 14', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 4, 2, 0, NULL),
(15, 'Mesa 15', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 17, 1, 0, 3),
(16, 'Mesa 16', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 17, 1, 0, NULL),
(17, 'Mesa 17', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 6, 2, 0, NULL),
(18, 'Mesa 18', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 5, 1, 0, 3),
(19, 'Mesa 19', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 2, 1, 0, NULL),
(20, 'Mesa 20', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 4, 2, 0, NULL);

--
-- Disparadores `mesas`
--
DELIMITER $$
CREATE TRIGGER `trg_log_asignacion_mesa` AFTER UPDATE ON `mesas` FOR EACH ROW BEGIN
    IF NEW.usuario_id <> OLD.usuario_id THEN
        INSERT INTO log_asignaciones_mesas (
            mesa_id,
            mesero_anterior_id,
            mesero_nuevo_id,
            fecha_cambio,
            usuario_que_asigna_id
        )
        VALUES (
            NEW.id,
            OLD.usuario_id,
            NEW.usuario_id,
            NOW(),
            @usuario_asignador_id
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_caja`
--

CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL,
  `corte_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `tipo_movimiento` enum('deposito','retiro') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `motivo` text NOT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos_caja`
--

INSERT INTO `movimientos_caja` (`id`, `corte_id`, `usuario_id`, `tipo_movimiento`, `monto`, `motivo`, `fecha`) VALUES
(6, 58, 1, 'deposito', 5000.00, 'deposito', '2025-08-28 03:00:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_insumos`
--

CREATE TABLE `movimientos_insumos` (
  `id` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste','traspaso') DEFAULT 'entrada',
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_destino_id` int(11) DEFAULT NULL,
  `insumo_id` int(11) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `qr_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ofertas_dia`
--

CREATE TABLE `ofertas_dia` (
  `id` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `vigente` tinyint(1) DEFAULT 1,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ofertas_dia`
--

INSERT INTO `ofertas_dia` (`id`, `descripcion`, `vigente`, `fecha`) VALUES
(1, '2x1 en aguas frescas', 1, '2025-07-28'),
(2, '10% de descuento en platillo del día', 1, '2025-07-28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `existencia` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `imagen` varchar(255) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `precio`, `descripcion`, `existencia`, `activo`, `imagen`, `categoria_id`) VALUES
(4, 'Refresco 600ml', 20.00, 'Refresco embotellado', 298, 1, NULL, 1),
(5, 'Rollo California', 120.00, 'Salmón, arroz, alga nori', 6, 1, NULL, 3),
(6, 'Guamuchilito', 109.00, 'Surimi, camarón empanizado, salsa de anguila', 6, 1, NULL, 8),
(7, 'Guerra', 125.00, 'Camarón, ajonjolí, aguacate, salsa de anguila', 6, 1, NULL, 8),
(8, 'Triton Roll', 125.00, 'Philadelphia, pepino, aguacate, surimi, atún ahumado, anguila, siracha', 6, 1, 'prod_68add2ff11cc1.jpg', 8),
(9, 'Mechas', 139.00, 'Philadelphia, pepino, aguacate, camarón, ajonjolí, kanikama, camarón empanizado,limón, sirracha, anguila, shisimi', 6, 1, NULL, 8),
(10, 'Supremo', 135.00, 'Surimi, philadelphia, ajonjolí, tampico,  pollo capeado, salsa de anguila', 6, 1, NULL, 8),
(11, 'Roka Crunch Roll', 119.00, 'Philadelphia, pepino, aguacate, camarón, surimi empanizado, zanahoria rallada, salsa de anguila', 6, 1, NULL, 8),
(12, 'Mar y Tierra', 105.00, 'Rollo relleno de carne y camarón.', 6, 1, NULL, 9),
(13, 'Cielo, Mar y Tierra', 109.00, 'Pollo, carne, camarón', 6, 1, NULL, 9),
(14, '3 Quesos', 115.00, 'Rollo de camarón, carne, base, queso americano\n y gratinado con queso chihuahua.', 6, 1, 'prod_68adcf8c73757.jpg', 9),
(15, 'Chiquilin Roll', 115.00, 'Relleno de base (philadelphia, pepino y\n aguacate) Por fuera topping de camarón\n empanizado especial, bañado en salsa de anguila\n y ajonjolí.', 5, 1, NULL, 9),
(16, 'Maki roll', 105.00, 'Rollo de 1 ingrediente a elegir (carne, tampico,\n pollo y camarón)', 6, 1, NULL, 9),
(17, 'Beef cheese', 119.00, 'Rollo de carne gratinado con queso spicy y\n ajonjolí.', 6, 1, NULL, 9),
(18, 'Cordon Blue', 115.00, 'Rollo relleno de carne y tocino forrado con\n philadelphia y gratinado con queso.', 6, 1, NULL, 9),
(19, 'Culichi Roll', 125.00, 'Rollo de carne con topping especial de tampico\n Tokyo empanizado coronado con camarón.', 6, 1, NULL, 9),
(20, 'Bacon Cheese', 125.00, 'Rollo de pollo por fuera gratinado con tocino.', 6, 1, 'prod_68add11ce2483.jpg', 9),
(21, 'Crunch Chicken', 125.00, 'Pollo empanizado, tocino, chile serrano, salsa bbq, salsa de anguila', 6, 1, NULL, 9),
(22, 'Kito', 119.00, 'Carne, tocino, queso, tampico', 6, 1, 'prod_68add69fe703c.jpg', 9),
(23, 'Norteño', 115.00, 'Camarón, tampico, queso, tocino, chile serrano', 6, 1, NULL, 9),
(24, 'Goloso Roll', 135.00, 'Res, pollo, tocino, queso o tampico', 6, 1, 'prod_68add37a08889.jpg', 9),
(25, 'Demon roll', 135.00, 'Res, tocino, toping demon (camarón enchiloso)', 6, 1, 'prod_68add435d40b1.jpg', 9),
(26, 'Nano Max', 245.00, 'Dedos de queso, dedos de surimi, carne, pollo, tocino, tampico, empanizado', 296, 1, NULL, 12),
(27, 'Nano XL', 325.00, 'Dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 1.5 kg', 297, 1, 'prod_68add3af36463.jpg', 12),
(28, 'Nano T-plus', 375.00, 'Dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 2 kg', 297, 1, NULL, 12),
(29, 'Chile Volcán', 85.00, 'Chile, 1 ingrediente a elegir, arroz, queso chihuahua,philadelphia', 274, 1, NULL, 10),
(30, 'Kushiagues', 75.00, 'Par de brochetas (camarón, pollo o surimi)', 292, 1, NULL, 10),
(31, 'Dedos de Queso', 69.00, 'Queso, empanizado (5 piezas)', 86, 1, NULL, 10),
(32, 'Tostada Culichi', 75.00, 'Tostada, camarón, pulpo, callo, pepino, cebolla morada, masago, chile serrano, chile en polvo, jugo de aguachile', 75, 1, 'prod_68add491b6f90.jpg', 10),
(33, 'Tostada tropical', 75.00, 'Tostada, atún, mango, camarón, callo, cebolla morada, chile en polvo, jugo de aguachile', 292, 1, NULL, 10),
(34, 'Empanada Horneada', 115.00, 'Tortilla de harina, carne, pollo, camarón,  mezcla de quesos, tampico, anguila y sirracha', 260, 1, NULL, 10),
(35, 'Rollitos', 75.00, 'Orden de 2 piezas, rellenos de philadelphia,\n queso chihuahua e ingrediente a elegir (res, pollo\n o camarón).', 274, 1, 'prod_68add227e7037.jpg', 10),
(36, 'Gyozas', 95.00, 'Orden con 6 piezas pequeñas (Pueden ser de\n philadelphia y camarón o de pollo y verduras)', 274, 1, NULL, 10),
(37, 'Papas a la francesa', 65.00, 'Papas a la francesa y cátsup ó aderezo especial', 298, 1, NULL, 10),
(38, 'Papas gajo', 75.00, 'Papas gajo y cátsup ó aderezo especial', 299, 1, NULL, 10),
(39, 'Ceviche Tokyo', 165.00, 'Cama de pepino, kanikama, camarón, aguacate, pulpo, jugo de aguachile', 271, 1, 'prod_68add2c342bb0.jpg', 3),
(40, 'Teriyaki krispy', 135.00, 'pollo empanizado, chile morrón, chile de arból, zanahoria, cebolla morada, cacahuate con salsa (salado)', 300, 1, NULL, 3),
(41, 'Teriyaki', 139.00, 'Ingrediente a elegir, salteado de cebolla, zanahoria, calabaza, brócoli y coliflor, salsa teriyaki (dulce)', 300, 1, NULL, 3),
(42, 'Pollo Mongol', 135.00, 'Pollo capeado, cebolla, zanahoria, apio, chile serrano, chile morrón, chile de arból, salsas orientales, montado en arroz blanco', 298, 1, 'prod_68add8fa7fb9e.jpg', 3),
(43, 'Chow Mein Especial', 155.00, 'Pasta frita, camarón, carne, pollo, vegetales, salsas orientales', 292, 1, 'prod_68adcfaa08c5a.jpg', 4),
(44, 'Chukasoba', 149.00, 'Camarón, pulpo, vegetales, pasta chukasoba', 292, 1, NULL, 4),
(45, 'Fideo Yurey', 165.00, 'Fideo chino transparente, julianas de zanahoria y apio, cebolla, chile caribe y morrón y la proteína de tu elección', 298, 1, NULL, 4),
(46, 'Udon spicy', 179.00, 'Julianas de zanahoria y cebolla, chile caribe, apio, chile de árbol, nuez de la india, ajonjolí, camarones capeados', 297, 1, 'prod_68add7d1cd5d9.jpg', 4),
(47, 'Orange Chiken Tokyo', 149.00, 'Pollo capeado (300gr), graby de naranja, pepino, zanahoria, rodajas de naranja, ajonjolíPollo capeando (300gr) rebosado con graby de\n naranja con zanahoria, pepino y rodajas de naranja\n y ajonjolí', 269, 1, NULL, 3),
(48, 'Udon Muchi', 125.00, 'Pasta udon, vegetales, camarón y pollo', 292, 1, NULL, 4),
(49, 'Tokyo ramen', 125.00, 'Pasta, vegetales, naruto, huevo, carne, camarón, fondo de res y cerdo', 292, 1, NULL, 4),
(50, 'Ramen Gran Meat', 125.00, 'Pasta, vegetales, trozos de carne sazonada con salsas orientales', 99, 1, NULL, 4),
(51, 'Ramen yasai', 115.00, 'Pasta, vegetales, fondo de res y cerdo', 300, 1, NULL, 4),
(52, 'Baby Ramen', 119.00, 'Pasta, vegetales, pollo a la plancha, salsas orientales, fondo de res y cerdo', 50, 1, NULL, 4),
(53, 'Cajun Ramen', 155.00, 'Fideos, vegetales, camarón gigante para pelar, fondo de res y cerdo, ajonjolí', 297, 1, NULL, 4),
(54, 'Gohan', 125.00, 'Arroz blanco, res y pollo, base de philadelphia y tampico con rodajas de aguacate, camarones empanizados, ajonjolí', 296, 1, NULL, 5),
(55, 'Gohan Krispy', 115.00, 'Arroz blanco, base de philadelphia, tampico y cubitos de aguacate, pollo y cebolla capeados, salsa de anguila, ajonjolí', 296, 1, 'prod_68add4bf039d2.jpg', 5),
(56, 'Yakimeshi', 115.00, 'Arroz frito, vegetales, carne, pollo y tocino, philadelphia, tampico, aguacate, ajonjolí', 271, 1, 'prod_68add0ace9c67.jpg', 5),
(57, 'Rollo Aguachile Especial', 125.00, 'Arroz frito, pollo empanizado, philadelphia, aguacate y tampico', 271, 1, 'prod_68add7b73652c.jpg', 5),
(58, 'Bomba', 115.00, 'Bola de arroz, res, pollo, philadelphia, queso chihuahua, tampico , empanizada y cubierta de salsa de anguila', 274, 1, 'prod_68add5bb666f3.jpg', 5),
(59, 'Menú kids 1', 79.00, '1/2 Rollo de pollo (6 piezas) y papas a la francesa', 100, 1, NULL, 3),
(60, 'Menú kids 2', 85.00, 'Yakimeshi mini y papas a la francesa', 300, 1, NULL, 3),
(61, 'Menú Kids 3', 79.00, 'Dedos de queso (3 piezas) y papas a la francesa', 100, 1, NULL, 3),
(62, 'Chocoflan', 49.00, 'Porción de chocoflan', 198, 1, NULL, 2),
(63, 'Pay de Queso', 49.00, 'Porción de pay de queso', 198, 1, 'prod_68ae01fd0820f.jpg', 2),
(64, 'Helado Tempura', 79.00, 'Helado tempura', 200, 1, NULL, 2),
(65, 'Postre Especial', 79.00, NULL, 198, 1, 'prod_68ae00d2cd4af.jpg', 2),
(66, 'Té de Jazmín (Litro)', 33.00, 'Té verde con aroma a jazmín, servido en litro.', 29, 1, NULL, 1),
(67, 'Té de Jazmín (Refil)', 35.00, 'Té verde aromatizado con flores de jazmín.', 28, 1, NULL, 1),
(68, 'Limonada Natural', 35.00, 'Bebida de limón exprimido con agua y azúcar.', 200, 1, NULL, 1),
(69, 'Limonada Mineral', 38.00, 'Bebida de limón con agua mineral y azúcar.', 200, 1, NULL, 1),
(70, 'Naranjada Natural', 35.00, 'Bebida de jugo de naranja con agua y azúcar.', 200, 1, NULL, 1),
(71, 'Naranjada Mineral', 38.00, 'Refresco de naranja con agua mineral.', 150, 1, NULL, 1),
(72, 'Agua de Tamarindo', 35.00, 'Bebida dulce y ácida de tamarindo.', 298, 1, NULL, 1),
(73, 'Agua Mineral (355ml)', 35.00, 'Agua con gas en envase pequeño.', 3000, 1, 'prod_68ae05aa8d01f.jpg', 1),
(74, 'Calpico', 35.00, 'Bebida japonesa dulce y láctea de yogur.', 300, 1, 'prod_68ae01959fac5.jpg', 1),
(75, 'Calpitamarindo', 39.00, NULL, 9, 1, NULL, 1),
(76, 'Refresco (335ml)', 29.00, 'Refresco embotellado', 2990, 1, 'prod_68ae07bd9ef3c.jpg', 1),
(77, 'Aderezo de Chipotle', 10.00, 'Salsa cremosa picante de chipotle.', 274, 1, 'prod_68ae00b788642.jpg', 6),
(78, 'Aderezo de Cilantro', 15.00, 'Salsa cremosa con cilantro fresco.', 288, 1, NULL, 6),
(79, 'Salsa Sriracha', 10.00, 'Alsa picante de chile, ajo y vinagre.', 195, 1, 'prod_68ae083f538d5.jpg', 6),
(80, 'Jugo de Aguachile', 15.00, 'Salsa líquida de limón, chile y especias usada para marinar mariscos.', 0, 1, NULL, 6),
(81, 'Ranch', 15.00, 'Aderezo cremoso de hierbas y especias.', 300, 1, 'prod_68ae011cd828d.jpg', 6),
(82, 'Búfalo', 15.00, 'Salsa picante de chile y mantequilla.', 300, 1, 'prod_68ae0164e5f57.jpg', 6),
(83, 'BBQ', 15.00, 'Salsa dulce y ahumada para carnes.', 300, 1, NULL, 6),
(84, 'Soya Extra', 10.00, 'Salsa de soja concentrada o adicional', 2000, 1, NULL, 6),
(85, 'Salsa de Anguila', 10.00, 'Salsa dulce y salada hecha con anguila y soja.', 199, 1, NULL, 6),
(86, 'Cebollitas o Chiles', 10.00, NULL, 29898, 1, NULL, 6),
(87, 'Topping Horneado Especial', 20.00, 'Aderezo de chipotle, anguila y sriracha', 182, 1, NULL, 7),
(88, 'Topping Kanikama', 35.00, '(Ensalada de cangrejo)', 96, 1, NULL, 7),
(89, 'Topping Tampico', 15.00, '(Ensalada de surimi)', 96, 1, NULL, 7),
(90, 'Topping Demon', 35.00, 'Camarón, tocino, quesos, serrano y chichimi', 48, 1, NULL, 7),
(91, 'Topping Chiquilín', 30.00, 'Camarón empanizado, anguila y ajonjolí', 46, 1, NULL, 7),
(92, 'Gladiador Roll', 139.00, 'Por dentro philadelphia, pepino y aguacate. Por fuera trozos de pulpo, queso spicy, shishimi y cebolla, bañado en salsa de anguila y ajonjolí. Rollo natural.', 6, 1, NULL, 9),
(93, 'Güerito Roll', 145.00, 'Por dentro camarón. Forrado con philadelphia y manchego, bañado en aderezo de chipotle, coronado con tocino, caribe y bañado en salsa sriracha. Empanizado.', 6, 1, 'prod_68add1fe92388.jpg', 9),
(94, 'Ebby Especial Roll', 145.00, 'Por dentro base, forrado con tampico cheese, bañado en aderezo de chipotle y coronado con camarón mariposa, aguacate, anguila y ajonjolí. Empanizado.', 6, 1, NULL, 9),
(95, 'Pakun Roll', 135.00, 'Relleno de tocino, por fuera topping de pollo y queso spicy, zanahoria. Acompañado de salsa anguila. Rollo natural.', 6, 1, NULL, 9),
(96, 'Rorris Roll', 135.00, 'Camarón y caribe por dentro, topping de tampico cheese, aguacate y bañados en salsa de anguila y ajonjolí. Empanizado.', 6, 1, NULL, 9),
(97, 'Royal Roll', 139.00, 'Carne y tocino por dentro, con topping de pollo. Empanizado, bañado con aderezo de chipotle, salsa de anguila y ajonjolí.', 6, 1, 'prod_68adcf0749621.jpg', 9),
(98, 'Larry Roll', 155.00, 'Rollo relleno de camarón, forrado con salmón. Topping de surimi finamente picado, spicy, coronado con atún fresco y bañado en salsa de anguila y ajonjolí.', 6, 1, 'prod_68add883e8ce7.jpg', 11),
(99, 'Aguachile Especial Roll', 155.00, 'Rollo relleno de philadelphia, pepino y aguacate. Forrado de chile serrano finamente picado, coronado con un aguachile especial de camarón, pulpo, callo y aguacate.', 6, 1, NULL, 11),
(100, 'Mordick Roll', 145.00, 'Rollo relleno de tocino, montado doble con queso gratinado, mezcla de quesos spicy, coronado con camarones empanizados y bañado en salsa de anguila y ajonjolí.', 6, 1, 'prod_68add0ea0033c.jpg', 11),
(101, 'Maney Roll', 165.00, 'Relleno de philadelphia, pepino y aguacate. Forrado de aguacate fresco y topping con camarón, medallón de atún, callo, mango y cebolla morada. Acompañado de salsa aguachile. Rollo natural.', 6, 1, 'prod_68add31666451.jpg', 11),
(102, 'Onigiri', 59.00, '1 Pieza de triángulo de arroz blanco, con un toque ligero de philadelphia, forrado de alga, cubierto de ajonjolí y relleno opcional de pollo con verduras (col morada y zanahoria) o atún con aderezo especial de mayonesa y cebollín.', 9, 1, 'prod_68add35c53114.jpg', 3),
(103, 'Dumplings', 95.00, 'Orden de 6 piezas de dumplings, rellenos de carne molida de cerdo. Sazonados orientalmente y acompañado con salsa macha.', 300, 1, 'prod_68add5b64497c.jpg', 3),
(104, 'Boneless', 135.00, '250gr. De boneless con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel o mermelada de chipotle).', 4, 1, 'prod_68ae02330ae4d.jpg', 3),
(105, 'Alitas', 135.00, '250gr. De alitas con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel ó mermelada de chipotle).', 12, 1, 'prod_68add564480c8.jpg', 3),
(106, 'Sopa Pho', 149.00, 'Rico fondo de pollo con vegetales, pechuga de pollo, fideos chinos y chile de árbol. Coronado con 4 piezas de dumplings.', 50, 1, 'prod_68adce4f32265.jpg', 3),
(107, 'Yummy Roll', 159.00, 'Alga por fuera, relleno de camarón, philadelphia, pepino y aguacate. Gratinado con queso spicy de la casa. Coronado con camarón, aguacate y bañado en salsa de anguila y ajonjolí.', 6, 1, 'prod_68add0c25fd53.jpg', 3),
(108, 'Cebolla Caramelizada', 10.00, 'Cebolla Caramelizada', 14948, 1, NULL, 6),
(109, 'Kintaro', 102.00, 'Plato de sushi con atún graso picado toro y cebollín', 6, 1, NULL, NULL),
(110, 'Guamuchilito Especial', 123.00, 'Bebida preparada con jugo de guamúchil, combinada con alcohol, salsas y especias.', 6, 1, 'prod_68add0071ee2a.jpg', 11),
(111, 'Juny', 333.00, 'Juny', 6, 1, NULL, NULL),
(112, 'Pork Spicy', 122.00, 'Platillo de cerdo picante.', 6, 1, 'prod_68adcf27bc6a4.jpg', 8),
(120, 'Corona 1/2', 35.00, 'Cerveza helada', 300, 1, 'prod_68adff38848ad.jpg', 1),
(121, 'Corona Golden Light 1/2', 35.00, 'Cerveza Golden helada', 300, 1, 'prod_68adff1b2eb67.jpg', 1),
(122, 'Negra Modelo', 40.00, 'Cerveza negra helada', 300, 1, 'prod_68adffa389931.jpg', 1),
(123, 'Modelo Especial', 40.00, 'Cerveza helada', 300, 1, 'prod_68adfeeac5c57.jpg', 1),
(124, 'Bud Light', 35.00, 'Cerveza helada', 292, 1, 'prod_68ae0111e5f9d.jpg', 1),
(125, 'Stella Artois', 45.00, 'Cerveza Helada', 300, 1, 'prod_68ae081b893ed.jpg', 1),
(126, 'Ultra 1/2', 45.00, 'Cerveza helada', 300, 1, 'prod_68ae015b16f22.jpg', 1),
(127, 'Michelob 1/2', 45.00, 'Cerveza helada', 300, 1, NULL, 1),
(128, 'Vaso Chelado', 10.00, 'Vaso chelado', 200, 1, NULL, 6),
(129, 'Vaso Michelado', 15.00, 'Vaso michelado', 200, 1, NULL, 6),
(130, 'Vaso Clamato', 25.00, 'Vaso michelado', 200, 1, NULL, 6),
(131, 'Cheese Fries', 109.00, 'Concha de papa gajo sazonada, bañada en delicioso queso y tozos de tocino; al horno', 30, 1, 'prod_68add54669456.jpg', 3),
(132, 'Charola Kyoyu Suru', 189.00, 'Camaron capeado, aros de cebolla y gyosas de carne de cerdo, acompañado de delicioso dip especial de la casa y salsa oriental', 48, 1, 'prod_68add217dc164.jpg', 3),
(133, 'Alitas Nuts', 149.00, 'Deliciosos 300grs De alitas, bañadas en salsa dulce con chile, sabor cacahuate y ajonjolí', 12, 1, NULL, 3),
(134, 'Edemamaes', 79.00, 'Vaina de frijol de soja preparado con picante, soya,sal y limon en una cama de zanahoria', 75, 1, 'prod_68add3a313461.jpg', 3),
(135, 'Crispy Chesse', 99.00, 'Rollo de 6 a 7 pz relleno de carne, philadelphia, pepino y aguacate, empanizado, gratinado spicy y trozos de tocino frito.', 7, 1, NULL, 9),
(136, 'Chummy Roll', 99.00, 'Rollo de 6 a 7 pz relleno de philadelphia, pepino y aguacate, coronado con tampico y camarón Empanizado, bando en salsa de Anguila y ajonjoli.', 7, 1, NULL, 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `telefono`, `direccion`) VALUES
(1, 'Suministros Sushi MX', '555-123-4567', 'Calle Soya #123, CDMX'),
(2, 'Pescados del Pacífico', '555-987-6543', 'Av. Mar #456, CDMX');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `qrs_insumo`
--

CREATE TABLE `qrs_insumo` (
  `id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `json_data` text DEFAULT NULL,
  `estado` enum('pendiente','confirmado','anulado') DEFAULT 'pendiente',
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `expiracion` datetime DEFAULT NULL,
  `pdf_envio` varchar(255) DEFAULT NULL,
  `pdf_recepcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas`
--

CREATE TABLE `recetas` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `insumo_id` int(11) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `recetas`
--

INSERT INTO `recetas` (`id`, `producto_id`, `insumo_id`, `cantidad`) VALUES
(1, 5, 1, 190.00),
(2, 5, 2, 0.50),
(3, 5, 3, 100.00),
(4, 4, 4, 1.00),
(5, 4, 72, 10.00),
(6, 5, 75, 10.00),
(7, 5, 2, 0.50),
(30, 10, 7, 50.00),
(31, 10, 12, 35.00),
(32, 10, 16, 10.00),
(33, 10, 81, 80.00),
(34, 10, 76, 10.00),
(75, 25, 8, 40.00),
(76, 26, 25, 10.00),
(77, 26, 59, 10.00),
(78, 26, 9, 10.00),
(79, 26, 8, 10.00),
(80, 26, 81, 10.00),
(81, 27, 25, 10.00),
(82, 27, 8, 10.00),
(83, 27, 81, 10.00),
(84, 28, 25, 10.00),
(85, 28, 8, 10.00),
(86, 28, 81, 10.00),
(87, 29, 11, 10.00),
(88, 29, 12, 10.00),
(89, 30, 10, 10.00),
(90, 30, 9, 10.00),
(91, 30, 7, 10.00),
(92, 32, 83, 1.00),
(93, 32, 10, 30.00),
(94, 32, 33, 30.00),
(95, 32, 41, 30.00),
(96, 32, 36, 30.00),
(97, 32, 29, 40.00),
(98, 32, 37, 20.00),
(99, 32, 21, 10.00),
(100, 32, 56, 5.00),
(101, 32, 63, 15.00),
(102, 33, 83, 10.00),
(103, 33, 40, 10.00),
(104, 33, 26, 10.00),
(105, 33, 10, 10.00),
(106, 33, 41, 10.00),
(107, 33, 29, 10.00),
(108, 33, 56, 10.00),
(109, 33, 63, 10.00),
(110, 34, 82, 10.00),
(111, 34, 9, 10.00),
(112, 34, 10, 10.00),
(113, 34, 66, 10.00),
(114, 34, 81, 10.00),
(115, 34, 19, 10.00),
(116, 35, 12, 10.00),
(117, 35, 11, 10.00),
(118, 36, 12, 10.00),
(119, 36, 10, 10.00),
(120, 36, 9, 10.00),
(121, 37, 39, 10.00),
(122, 38, 39, 10.00),
(123, 39, 23, 10.00),
(124, 39, 10, 10.00),
(125, 39, 24, 10.00),
(126, 39, 33, 10.00),
(127, 39, 63, 10.00),
(128, 40, 22, 10.00),
(129, 40, 34, 10.00),
(130, 40, 29, 10.00),
(131, 40, 52, 10.00),
(132, 41, 34, 10.00),
(133, 41, 53, 10.00),
(134, 41, 44, 10.00),
(135, 41, 57, 10.00),
(136, 41, 77, 10.00),
(137, 42, 55, 10.00),
(138, 42, 34, 10.00),
(139, 42, 35, 10.00),
(140, 42, 21, 10.00),
(141, 42, 22, 10.00),
(142, 42, 78, 10.00),
(143, 43, 70, 10.00),
(144, 43, 10, 10.00),
(145, 43, 9, 10.00),
(146, 43, 78, 10.00),
(147, 44, 10, 10.00),
(148, 44, 33, 10.00),
(149, 44, 69, 10.00),
(150, 45, 43, 10.00),
(151, 45, 64, 10.00),
(152, 45, 35, 10.00),
(153, 45, 55, 10.00),
(154, 45, 32, 10.00),
(155, 45, 67, 10.00),
(156, 46, 64, 10.00),
(157, 46, 55, 10.00),
(158, 46, 32, 10.00),
(159, 46, 35, 10.00),
(160, 46, 45, 10.00),
(161, 46, 38, 10.00),
(162, 46, 16, 10.00),
(163, 47, 62, 10.00),
(164, 47, 36, 10.00),
(165, 47, 34, 10.00),
(166, 47, 74, 10.00),
(167, 47, 16, 10.00),
(168, 48, 46, 10.00),
(169, 48, 10, 10.00),
(170, 48, 9, 10.00),
(171, 49, 50, 10.00),
(172, 49, 47, 10.00),
(173, 49, 10, 10.00),
(174, 49, 61, 10.00),
(175, 49, 48, 10.00),
(177, 51, 61, 10.00),
(178, 51, 48, 10.00),
(179, 52, 78, 20.00),
(180, 52, 61, 120.00),
(181, 52, 48, 60.00),
(182, 53, 60, 10.00),
(183, 53, 54, 10.00),
(184, 53, 61, 10.00),
(185, 53, 48, 10.00),
(186, 53, 16, 10.00),
(187, 54, 13, 10.00),
(189, 54, 9, 10.00),
(190, 54, 16, 10.00),
(191, 55, 13, 10.00),
(192, 55, 81, 10.00),
(193, 55, 9, 10.00),
(194, 55, 76, 10.00),
(195, 55, 16, 10.00),
(196, 56, 9, 10.00),
(197, 56, 8, 10.00),
(198, 56, 12, 10.00),
(199, 56, 81, 10.00),
(200, 56, 24, 10.00),
(201, 56, 16, 10.00),
(202, 57, 12, 10.00),
(203, 57, 24, 10.00),
(204, 57, 81, 10.00),
(206, 58, 9, 10.00),
(207, 58, 12, 10.00),
(208, 58, 11, 10.00),
(209, 58, 81, 10.00),
(210, 60, 85, 10.00),
(362, 25, 14, 30.00),
(367, 5, 90, 45.00),
(384, 14, 24, 25.00),
(385, 14, 10, 25.00),
(386, 14, 14, 40.00),
(387, 14, 36, 25.00),
(388, 14, 12, 45.00),
(389, 14, 15, 2.00),
(390, 14, 66, 80.00),
(391, 14, 1, 190.00),
(392, 14, 2, 0.50),
(393, 20, 24, 25.00),
(394, 20, 36, 25.00),
(395, 20, 12, 25.00),
(396, 20, 66, 80.00),
(397, 20, 21, 5.00),
(398, 20, 8, 70.00),
(399, 20, 1, 190.00),
(400, 20, 2, 0.50),
(401, 17, 87, 25.00),
(402, 17, 24, 25.00),
(403, 17, 14, 50.00),
(404, 17, 36, 25.00),
(405, 17, 12, 25.00),
(406, 17, 66, 80.00),
(407, 17, 80, 15.00),
(408, 17, 1, 190.00),
(409, 17, 2, 0.50),
(410, 5, 24, 25.00),
(411, 5, 10, 45.00),
(412, 5, 36, 25.00),
(413, 5, 12, 25.00),
(414, 18, 24, 25.00),
(415, 18, 14, 30.00),
(416, 18, 36, 25.00),
(417, 18, 12, 45.00),
(418, 18, 66, 80.00),
(419, 18, 8, 15.00),
(420, 18, 1, 190.00),
(421, 18, 2, 0.50),
(422, 21, 24, 25.00),
(423, 21, 19, 2.00),
(424, 21, 20, 4.00),
(425, 21, 92, 5.00),
(426, 21, 36, 25.00),
(427, 21, 12, 25.00),
(428, 21, 9, 50.00),
(429, 21, 91, 150.00),
(430, 21, 1, 190.00),
(431, 21, 2, 0.50),
(432, 19, 87, 25.00),
(433, 19, 24, 25.00),
(434, 19, 19, 20.00),
(435, 19, 10, 50.00),
(436, 19, 14, 50.00),
(437, 19, 36, 25.00),
(438, 19, 12, 25.00),
(439, 19, 81, 80.00),
(440, 19, 1, 190.00),
(441, 19, 2, 0.50),
(442, 13, 24, 25.00),
(443, 13, 10, 20.00),
(444, 13, 14, 20.00),
(445, 13, 36, 25.00),
(446, 13, 12, 25.00),
(447, 13, 9, 20.00),
(448, 13, 1, 190.00),
(449, 13, 2, 0.50),
(450, 25, 87, 25.00),
(451, 25, 24, 25.00),
(452, 25, 10, 25.00),
(453, 25, 36, 25.00),
(454, 25, 12, 25.00),
(455, 25, 66, 80.00),
(456, 25, 21, 10.00),
(457, 25, 80, 10.00),
(458, 25, 1, 190.00),
(459, 25, 2, 0.50),
(460, 94, 87, 25.00),
(461, 94, 24, 25.00),
(462, 94, 19, 10.00),
(463, 94, 10, 50.00),
(464, 94, 88, 80.00),
(465, 94, 36, 40.00),
(466, 94, 12, 25.00),
(467, 94, 1, 190.00),
(468, 94, 2, 0.50),
(469, 92, 87, 25.00),
(470, 92, 24, 25.00),
(471, 92, 88, 80.00),
(472, 92, 36, 40.00),
(473, 92, 12, 25.00),
(474, 92, 33, 40.00),
(475, 92, 1, 190.00),
(476, 92, 2, 0.50),
(477, 6, 24, 65.00),
(478, 6, 36, 25.00),
(479, 6, 12, 25.00),
(480, 6, 7, 50.00),
(481, 6, 81, 80.00),
(482, 6, 1, 190.00),
(483, 6, 2, 0.50),
(484, 110, 24, 65.00),
(485, 110, 10, 50.00),
(486, 110, 36, 25.00),
(487, 110, 12, 25.00),
(488, 110, 7, 50.00),
(489, 110, 81, 80.00),
(490, 110, 1, 190.00),
(491, 110, 2, 0.50),
(492, 93, 87, 25.00),
(493, 93, 24, 25.00),
(494, 93, 10, 45.00),
(495, 93, 92, 5.00),
(496, 93, 32, 5.00),
(497, 93, 36, 25.00),
(498, 93, 12, 25.00),
(499, 93, 66, 80.00),
(500, 93, 80, 10.00),
(501, 93, 8, 15.00),
(502, 93, 1, 190.00),
(503, 93, 2, 0.50),
(504, 7, 87, 25.00),
(505, 7, 24, 50.00),
(506, 7, 10, 45.00),
(507, 7, 90, 25.00),
(508, 7, 36, 25.00),
(509, 7, 12, 25.00),
(510, 7, 80, 15.00),
(511, 7, 1, 190.00),
(512, 7, 2, 0.50),
(513, 24, 24, 25.00),
(514, 24, 14, 20.00),
(515, 24, 88, 80.00),
(516, 24, 36, 25.00),
(517, 24, 12, 25.00),
(518, 24, 9, 20.00),
(519, 24, 81, 80.00),
(520, 24, 8, 15.00),
(521, 24, 1, 190.00),
(522, 24, 2, 0.50),
(523, 111, 93, 4.00),
(524, 111, 24, 25.00),
(525, 111, 19, 10.00),
(526, 111, 92, 10.00),
(527, 111, 36, 25.00),
(528, 111, 12, 40.00),
(529, 111, 3, 80.00),
(530, 111, 1, 190.00),
(531, 111, 2, 0.50),
(532, 109, 24, 25.00),
(533, 109, 92, 5.00),
(534, 109, 62, 6.00),
(535, 109, 36, 25.00),
(536, 109, 12, 25.00),
(537, 109, 91, 150.00),
(538, 109, 1, 190.00),
(539, 109, 2, 0.50),
(540, 22, 24, 25.00),
(541, 22, 14, 30.00),
(542, 22, 36, 25.00),
(543, 22, 12, 25.00),
(544, 22, 66, 80.00),
(545, 22, 81, 40.00),
(546, 22, 8, 15.00),
(547, 22, 1, 190.00),
(548, 22, 2, 0.50),
(549, 98, 87, 20.00),
(550, 98, 24, 25.00),
(551, 98, 40, 25.00),
(552, 98, 10, 45.00),
(553, 98, 92, 5.00),
(554, 98, 36, 25.00),
(555, 98, 12, 35.00),
(556, 98, 3, 30.00),
(557, 98, 80, 5.00),
(558, 98, 1, 190.00),
(559, 98, 2, 0.50),
(560, 101, 24, 60.00),
(561, 101, 40, 30.00),
(562, 101, 41, 30.00),
(563, 101, 10, 30.00),
(564, 101, 26, 30.00),
(565, 101, 36, 40.00),
(566, 101, 12, 35.00),
(567, 101, 33, 30.00),
(568, 101, 63, 4.00),
(569, 101, 1, 190.00),
(570, 101, 2, 0.50),
(571, 9, 87, 25.00),
(572, 9, 24, 25.00),
(573, 9, 10, 45.00),
(574, 9, 90, 50.00),
(575, 9, 92, 5.00),
(576, 9, 73, 50.00),
(577, 9, 65, 5.00),
(578, 9, 36, 25.00),
(579, 9, 12, 25.00),
(580, 9, 1, 190.00),
(581, 9, 2, 0.50),
(582, 100, 24, 25.00),
(583, 100, 19, 10.00),
(584, 100, 90, 50.00),
(585, 100, 88, 80.00),
(586, 100, 36, 25.00),
(587, 100, 12, 25.00),
(588, 100, 8, 35.00),
(589, 100, 1, 190.00),
(590, 100, 2, 0.50),
(591, 16, 24, 25.00),
(592, 16, 10, 45.00),
(593, 16, 14, 50.00),
(594, 16, 36, 25.00),
(595, 16, 12, 25.00),
(596, 16, 9, 50.00),
(597, 16, 81, 70.00),
(598, 16, 1, 190.00),
(599, 16, 2, 0.50),
(600, 12, 24, 25.00),
(601, 12, 10, 25.00),
(602, 12, 14, 40.00),
(603, 12, 36, 25.00),
(604, 12, 12, 25.00),
(605, 12, 1, 190.00),
(606, 12, 2, 0.50),
(607, 23, 24, 25.00),
(608, 23, 10, 25.00),
(609, 23, 36, 25.00),
(610, 23, 12, 25.00),
(611, 23, 66, 80.00),
(612, 23, 21, 10.00),
(613, 23, 81, 35.00),
(614, 23, 8, 25.00),
(615, 23, 1, 190.00),
(616, 23, 2, 0.50),
(617, 95, 87, 25.00),
(618, 95, 24, 25.00),
(619, 95, 88, 80.00),
(620, 95, 36, 25.00),
(621, 95, 12, 25.00),
(622, 95, 9, 40.00),
(623, 95, 8, 35.00),
(624, 95, 34, 20.00),
(625, 95, 1, 190.00),
(626, 95, 2, 0.50),
(627, 112, 87, 25.00),
(628, 112, 24, 25.00),
(629, 112, 14, 25.00),
(630, 112, 36, 25.00),
(631, 112, 12, 25.00),
(632, 112, 66, 80.00),
(633, 112, 80, 10.00),
(634, 112, 8, 10.00),
(635, 112, 1, 190.00),
(636, 112, 2, 0.50),
(637, 11, 87, 25.00),
(638, 11, 24, 25.00),
(639, 11, 90, 25.00),
(640, 11, 36, 40.00),
(641, 11, 12, 25.00),
(642, 11, 80, 10.00),
(643, 11, 7, 50.00),
(644, 11, 34, 25.00),
(645, 11, 1, 190.00),
(646, 11, 2, 0.50),
(647, 96, 87, 25.00),
(648, 96, 24, 50.00),
(649, 96, 10, 45.00),
(650, 96, 32, 5.00),
(651, 96, 88, 80.00),
(652, 96, 36, 25.00),
(653, 96, 12, 25.00),
(654, 96, 81, 80.00),
(655, 96, 1, 190.00),
(656, 96, 2, 0.50),
(657, 97, 87, 25.00),
(658, 97, 24, 25.00),
(659, 97, 19, 10.00),
(660, 97, 14, 30.00),
(661, 97, 88, 80.00),
(662, 97, 36, 25.00),
(663, 97, 12, 25.00),
(664, 97, 9, 25.00),
(665, 97, 8, 15.00),
(666, 97, 1, 190.00),
(667, 97, 2, 0.50),
(668, 10, 87, 25.00),
(669, 10, 24, 25.00),
(670, 10, 36, 25.00),
(671, 10, 91, 50.00),
(672, 10, 80, 10.00),
(673, 10, 1, 190.00),
(674, 10, 2, 0.50),
(675, 8, 87, 20.00),
(676, 8, 24, 25.00),
(677, 8, 51, 70.00),
(678, 8, 92, 5.00),
(679, 8, 36, 40.00),
(680, 8, 12, 25.00),
(681, 8, 80, 5.00),
(682, 8, 7, 5.00),
(683, 8, 1, 190.00),
(684, 8, 2, 0.50),
(685, 107, 87, 25.00),
(686, 107, 24, 75.00),
(687, 107, 10, 50.00),
(688, 107, 36, 25.00),
(689, 107, 12, 25.00),
(690, 107, 9, 25.00),
(691, 107, 66, 80.00),
(692, 107, 21, 5.00),
(693, 107, 80, 10.00),
(694, 107, 8, 15.00),
(695, 107, 1, 190.00),
(696, 107, 2, 0.50),
(697, 15, 87, 40.00),
(698, 15, 24, 25.00),
(699, 15, 90, 80.00),
(700, 15, 36, 40.00),
(701, 15, 12, 25.00),
(702, 15, 1, 190.00),
(703, 15, 2, 0.50),
(704, 31, 15, 30.00),
(705, 31, 11, 30.00),
(706, 31, 66, 30.00),
(707, 50, 29, 30.00),
(708, 50, 53, 30.00),
(709, 50, 34, 30.00),
(710, 50, 35, 30.00),
(711, 50, 73, 30.00),
(712, 62, 95, 1.00),
(713, 63, 96, 1.00),
(714, 64, 97, 1.00),
(715, 65, 98, 1.00),
(716, 77, 87, 10.00),
(717, 85, 19, 15.00),
(718, 128, 56, 15.00),
(719, 130, 56, 15.00),
(720, 129, 56, 15.00),
(721, 120, 101, 1.00),
(722, 121, 102, 1.00),
(723, 127, 108, 1.00),
(724, 126, 107, 1.00),
(725, 122, 103, 1.00),
(726, 123, 104, 1.00),
(727, 124, 105, 1.00),
(728, 125, 106, 1.00),
(729, 108, 55, 0.20),
(730, 86, 45, 0.10),
(731, 86, 55, 0.10),
(732, 76, 72, 1.00),
(733, 106, 45, 0.20),
(734, 106, 53, 20.00),
(735, 106, 34, 20.00),
(736, 106, 49, 4.00),
(737, 106, 73, 60.00),
(738, 106, 35, 10.00),
(739, 132, 10, 60.00),
(740, 132, 55, 20.00),
(741, 132, 49, 6.00),
(742, 132, 48, 60.00),
(743, 132, 87, 10.00),
(744, 131, 28, 100.00),
(745, 131, 11, 60.00),
(746, 131, 8, 30.00),
(747, 131, 55, 10.00),
(748, 105, 109, 250.00),
(749, 133, 109, 250.00),
(750, 133, 52, 20.00),
(751, 133, 89, 30.00),
(752, 133, 45, 10.00),
(753, 83, 20, 10.00),
(754, 81, 110, 10.00),
(755, 82, 99, 10.00),
(756, 84, 30, 15.00),
(757, 91, 90, 60.00),
(758, 91, 16, 2.00),
(759, 91, 19, 10.00),
(760, 90, 10, 60.00),
(761, 90, 8, 30.00),
(762, 90, 11, 30.00),
(763, 90, 21, 10.00),
(764, 90, 112, 5.00),
(765, 88, 23, 30.00),
(766, 88, 89, 30.00),
(767, 89, 7, 30.00),
(768, 89, 89, 30.00),
(773, 87, 19, 10.00),
(774, 87, 80, 15.00),
(775, 87, 87, 15.00),
(776, 74, 113, 1.00),
(777, 134, 114, 30.00),
(778, 134, 34, 40.00),
(779, 134, 45, 10.00),
(780, 104, 115, 250.00),
(781, 104, 89, 30.00),
(782, 102, 1, 120.00),
(783, 102, 12, 30.00),
(784, 102, 2, 0.50),
(785, 102, 16, 5.00),
(786, 102, 36, 25.00),
(787, 102, 34, 30.00),
(788, 102, 92, 20.00),
(789, 99, 1, 190.00),
(790, 99, 36, 40.00),
(791, 99, 12, 30.00),
(792, 99, 2, 0.50),
(793, 99, 45, 10.00),
(794, 99, 94, 40.00),
(795, 99, 33, 40.00),
(796, 99, 24, 30.00),
(797, 79, 80, 15.00),
(798, 73, 117, 1.00),
(799, 71, 31, 20.00),
(800, 71, 117, 1.00),
(801, 69, 65, 15.00),
(802, 69, 117, 1.00),
(803, 78, 118, 10.00),
(804, 78, 89, 10.00),
(805, 72, 116, 1.00),
(806, 68, 116, 1.00),
(807, 68, 65, 15.00),
(808, 70, 116, 1.00),
(809, 70, 31, 15.00),
(810, 66, 119, 1000.00),
(811, 67, 119, 1000.00),
(812, 135, 1, 150.00),
(813, 135, 2, 0.50),
(814, 135, 36, 25.00),
(815, 135, 12, 25.00),
(816, 135, 24, 25.00),
(817, 135, 17, 25.00),
(818, 135, 8, 25.00),
(819, 136, 1, 150.00),
(820, 136, 2, 0.50),
(821, 136, 36, 25.00),
(822, 136, 12, 25.00),
(823, 136, 24, 25.00),
(824, 136, 90, 25.00),
(825, 136, 81, 10.00),
(826, 136, 16, 2.00),
(827, 103, 59, 10.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `repartidores`
--

CREATE TABLE `repartidores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `repartidores`
--

INSERT INTO `repartidores` (`id`, `nombre`, `telefono`) VALUES
(1, 'Didi', '555-000-1111'),
(2, 'Rappi', '555-999-2222'),
(3, 'Uber', NULL),
(4, 'Repartidor casa', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `path` varchar(255) NOT NULL,
  `tipo` enum('link','dropdown','dropdown-item') NOT NULL,
  `grupo` varchar(50) DEFAULT NULL,
  `orden` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rutas`
--

INSERT INTO `rutas` (`id`, `nombre`, `path`, `tipo`, `grupo`, `orden`) VALUES
(1, 'Inicio', '/vistas/index.php', 'link', NULL, 1),
(2, 'Productos', '#', 'dropdown', 'Productos', 3),
(3, 'Insumos', '/vistas/insumos/insumos.php', 'dropdown-item', 'Productos', 1),
(4, 'Inventario', '/vistas/inventario/inventario.php', 'dropdown-item', 'Productos', 2),
(5, 'Recetas', '/vistas/recetas/recetas.php', 'dropdown-item', 'Productos', 3),
(6, 'Cocina', '/vistas/cocina/cocina2.php', 'link', NULL, 4),
(7, 'Ventas', '/vistas/ventas/ventas.php', 'link', NULL, 5),
(8, 'Cortes', '/vistas/corte_caja/corte.php', 'link', NULL, 6),
(9, 'Repartos', '/vistas/repartidores/repartos.php', 'link', NULL, 7),
(10, 'Mesas', '/vistas/mesas/mesas.php', 'link', NULL, 8),
(11, 'Más', '#', 'dropdown', 'Más', 9),
(12, 'Horarios', '/vistas/horarios/horarios.php', 'dropdown-item', 'Más', 1),
(13, 'Ticket', '/vistas/ventas/ticket.php', 'dropdown-item', 'Más', 2),
(14, 'Reportes', '/vistas/reportes/reportes.php', 'dropdown-item', 'Más', 3),
(15, 'Ayuda', '/vistas/ayuda.php', 'link', NULL, 10),
(18, 'Generar QR', '/vistas/bodega/generar_qr.php', 'dropdown-item', 'Más', 4),
(19, 'Recibir QR', '/vistas/bodega/recepcion_qr.php', 'dropdown-item', 'Más', 5),
(20, 'Meseros', '/vistas/mesas/asignar.php', 'link', NULL, 11),
(21, 'Reporteria', '/vistas/reportes/vistas_db.php', 'link', NULL, 12),
(22, 'Usuarios', '/vistas/usuarios/usuarios.php', 'dropdown-item', 'Más', 6),
(23, 'Rutas', '/vistas/rutas/rutas.php', 'dropdown-item', 'Más', 7),
(24, 'Permisos', '/vistas/rutas/urutas.php', 'dropdown-item', 'Más', 8);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` text NOT NULL,
  `rfc` varchar(20) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `web` varchar(100) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id`, `nombre`, `direccion`, `rfc`, `telefono`, `correo`, `web`, `activo`) VALUES
(1, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'ventas@tokyo.com', 'tokyosushi.com', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `folio` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `monto_recibido` decimal(10,2) DEFAULT 0.00,
  `tipo_pago` enum('efectivo','boucher','cheque') DEFAULT 'efectivo',
  `sede_id` int(11) DEFAULT NULL,
  `mesa_nombre` varchar(50) DEFAULT NULL,
  `mesero_nombre` varchar(100) DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `tiempo_servicio` int(11) DEFAULT NULL COMMENT 'Minutos de servicio',
  `nombre_negocio` varchar(100) DEFAULT NULL,
  `direccion_negocio` text DEFAULT NULL,
  `rfc_negocio` varchar(20) DEFAULT NULL,
  `telefono_negocio` varchar(20) DEFAULT NULL,
  `tipo_entrega` enum('mesa','domicilio','rapido') DEFAULT 'mesa',
  `tarjeta_marca_id` int(11) DEFAULT NULL,
  `tarjeta_banco_id` int(11) DEFAULT NULL,
  `boucher` varchar(50) DEFAULT NULL,
  `cheque_numero` varchar(50) DEFAULT NULL,
  `cheque_banco_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id`, `venta_id`, `folio`, `total`, `fecha`, `usuario_id`, `monto_recibido`, `tipo_pago`, `sede_id`, `mesa_nombre`, `mesero_nombre`, `fecha_inicio`, `fecha_fin`, `tiempo_servicio`, `nombre_negocio`, `direccion_negocio`, `rfc_negocio`, `telefono_negocio`, `tipo_entrega`, `tarjeta_marca_id`, `tarjeta_banco_id`, `boucher`, `cheque_numero`, `cheque_banco_id`) VALUES
(138, 125, 2012, 476.00, '2025-08-28 02:56:22', NULL, 476.00, 'efectivo', 1, 'Venta rápida', 'gilberto ozuna carrillo', NULL, '2025-08-28 02:56:22', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(140, 127, 2014, 145.00, '2025-08-28 03:15:56', NULL, 145.00, 'cheque', 1, 'Venta rápida', 'juan hernesto ortega Almanza', NULL, '2025-08-28 03:15:56', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, 'dseww', 6),
(141, 127, 2015, 99.00, '2025-08-28 03:15:56', NULL, 100.00, 'efectivo', 1, 'Venta rápida', 'juan hernesto ortega Almanza', NULL, '2025-08-28 03:15:56', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(142, 127, 2016, 115.00, '2025-08-28 03:15:56', NULL, 115.00, 'boucher', 1, 'Venta rápida', 'juan hernesto ortega Almanza', NULL, '2025-08-28 03:15:56', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', 1, 2, '20003343', NULL, NULL),
(143, 128, 2017, 49.00, '2025-08-28 03:26:43', NULL, 49.00, 'boucher', 1, 'Venta rápida', 'juan hernesto ortega Almanza', NULL, '2025-08-28 03:26:43', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', 1, 6, '4366', NULL, NULL),
(144, 129, 2018, 10.00, '2025-08-28 03:31:14', NULL, 10.00, 'boucher', 1, 'Venta rápida', 'gilberto ozuna carrillo', NULL, '2025-08-28 03:31:14', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', 2, 8, '3453443342', NULL, NULL),
(145, 130, 2019, 115.00, '2025-08-29 01:01:09', NULL, 120.00, 'efectivo', 1, 'Venta rápida', 'gilberto ozuna carrillo', NULL, '2025-08-29 01:01:09', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_detalles`
--

CREATE TABLE `ticket_detalles` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_detalles`
--

INSERT INTO `ticket_detalles` (`id`, `ticket_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(206, 138, 17, 4, 119.00),
(211, 140, 93, 1, 145.00),
(212, 141, 136, 1, 99.00),
(213, 142, 15, 1, 115.00),
(214, 143, 62, 1, 49.00),
(215, 144, 77, 1, 10.00),
(216, 145, 15, 1, 115.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('cajero','mesero','admin','repartidor','cocinero','barra','alimentos') NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `contrasena`, `rol`, `activo`) VALUES
(1, 'Administrador', 'admin', 'admin', 'admin', 1),
(2, 'Javier Emanuel lopez lozano', 'JavierE', 'd033e22ae348aeb5660fc2140aec35850c4da997', 'mesero', 1),
(3, 'maria jose valle tovar', 'MariaJ', 'admin', 'cajero', 1),
(4, 'juan hernesto ortega Almanza', 'juan', 'admin', 'mesero', 1),
(5, 'gilberto ozuna carrillo', 'Gil', 'admin', 'mesero', 1),
(6, 'alinne Guadalupe Gurrola ramirez', 'Alinne', 'd033e22ae348aeb5660fc2140aec35850c4da997', 'mesero', 1),
(7, 'Mesas general', 'mesas', 'admin', 'mesero', 1),
(8, 'Jose Angel Valdez Flores', 'AngelV', 'admin', 'cocinero', 1),
(9, 'Daniel Gutierrez Amador', 'DaniG', 'admin', 'cocinero', 1),
(10, 'Edson Darihec Reyes Villa', 'Darihec ', 'admin', 'cocinero', 1),
(11, 'Hector Osbaldo Hernandez Orona', 'HectorO', 'admin', 'cocinero', 1),
(12, 'Henry Adahyr Coronel Gamiz', 'Henry ', 'admin', 'cocinero', 1),
(13, 'Jose Arturo Montoya Campos', 'JoseA', 'admin', 'cocinero', 1),
(14, 'Kevin de Jesus Rosales Valles', 'KevinJ', 'admin', 'cocinero', 1),
(15, 'Roberto Garcia Soto', 'RobertoG', 'admin', 'cocinero', 1),
(16, 'Luis Varela Rueda', 'LuisV', 'admin', 'cocinero', 1),
(17, 'Jesus', 'Jesus', 'admin', 'mesero', 1),
(18, 'Andrea Jaqueline perez arrellano ', 'AndreaJ', 'admin', 'cajero', 1),
(31, 'Andrea ontivero escalera', 'AndreaO', 'admin', 'cajero', 1),
(32, 'Cajero General', 'CajeroG', 'admin', 'cajero', 1),
(33, 'Cocina General', 'CocinaG', 'admin', 'alimentos', 1),
(34, 'Barra General', 'BarraG', 'admin', 'barra', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_ruta`
--

CREATE TABLE `usuario_ruta` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ruta_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario_ruta`
--

INSERT INTO `usuario_ruta` (`id`, `usuario_id`, `ruta_id`) VALUES
(1, 1, 1),
(2, 1, 2),
(3, 1, 3),
(4, 1, 4),
(5, 1, 5),
(6, 1, 6),
(7, 1, 7),
(8, 1, 8),
(9, 1, 9),
(10, 1, 10),
(11, 1, 11),
(12, 1, 12),
(13, 1, 13),
(14, 1, 14),
(15, 1, 15),
(44, 1, 18),
(45, 1, 19),
(53, 1, 20),
(55, 1, 21),
(46, 1, 22),
(63, 1, 23),
(64, 1, 24),
(62, 2, 1),
(32, 2, 6),
(52, 2, 7),
(56, 2, 9),
(33, 2, 10),
(48, 2, 13),
(51, 2, 18),
(50, 2, 19),
(54, 2, 20),
(38, 3, 6),
(36, 3, 7),
(37, 3, 8),
(57, 3, 9),
(47, 4, 1),
(34, 4, 6),
(42, 4, 9),
(35, 4, 10),
(49, 5, 1),
(39, 5, 3),
(40, 5, 5),
(41, 5, 6),
(71, 32, 1),
(72, 32, 6),
(73, 32, 7),
(74, 32, 15),
(65, 33, 1),
(66, 33, 6),
(67, 33, 15),
(68, 34, 1),
(69, 34, 6),
(70, 34, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `mesa_id` int(11) DEFAULT NULL,
  `repartidor_id` int(11) DEFAULT NULL,
  `tipo_entrega` enum('mesa','domicilio','rapido') DEFAULT 'mesa',
  `usuario_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT 0.00,
  `estatus` enum('activa','cerrada','cancelada') DEFAULT 'activa',
  `entregado` tinyint(1) DEFAULT 0,
  `estado_entrega` enum('pendiente','en_camino','entregado') DEFAULT 'pendiente',
  `fecha_asignacion` datetime DEFAULT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_entrega` datetime DEFAULT NULL,
  `seudonimo_entrega` varchar(100) DEFAULT NULL,
  `foto_entrega` varchar(255) DEFAULT NULL,
  `corte_id` int(11) DEFAULT NULL,
  `cajero_id` int(11) DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `sede_id` int(11) DEFAULT NULL,
  `propina_efectivo` decimal(10,2) NOT NULL,
  `propina_cheque` decimal(10,2) NOT NULL,
  `propina_tarjeta` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `fecha`, `mesa_id`, `repartidor_id`, `tipo_entrega`, `usuario_id`, `total`, `estatus`, `entregado`, `estado_entrega`, `fecha_asignacion`, `fecha_inicio`, `fecha_entrega`, `seudonimo_entrega`, `foto_entrega`, `corte_id`, `cajero_id`, `observacion`, `sede_id`, `propina_efectivo`, `propina_cheque`, `propina_tarjeta`) VALUES
(120, '2025-08-27 11:12:37', NULL, NULL, 'rapido', 6, 0.00, 'cancelada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 58, 1, '', 1, 0.00, 0.00, 0.00),
(121, '2025-08-27 11:33:50', NULL, NULL, 'rapido', 2, 0.00, 'cancelada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 58, 1, '', 1, 0.00, 0.00, 0.00),
(122, '2025-08-27 11:58:47', NULL, NULL, 'rapido', 6, 0.00, 'cancelada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 58, 1, '', 1, 0.00, 0.00, 0.00),
(124, '2025-08-27 16:09:28', NULL, NULL, 'rapido', 5, 0.00, 'cancelada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 58, 1, '', 1, 0.00, 0.00, 0.00),
(125, '2025-08-27 18:55:53', NULL, NULL, 'rapido', 5, 476.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 58, 1, '', 1, 0.00, 0.00, 0.00),
(127, '2025-08-27 19:14:36', NULL, NULL, 'rapido', 4, 359.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 59, 1, '', 1, 0.00, 0.00, 0.00),
(128, '2025-08-27 19:25:29', NULL, NULL, 'rapido', 4, 49.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 60, 1, '', 1, 0.00, 0.00, 0.00),
(129, '2025-08-27 19:30:40', NULL, NULL, 'rapido', 5, 10.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 61, 1, '', 1, 0.00, 0.00, 0.00),
(130, '2025-08-28 16:59:24', NULL, NULL, 'rapido', 5, 115.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, NULL, 1, '', 1, 0.00, 0.00, 0.00);

--
-- Disparadores `ventas`
--
DELIMITER $$
CREATE TRIGGER `trg_log_cancel_venta` AFTER UPDATE ON `ventas` FOR EACH ROW BEGIN
  IF COALESCE(@ARCHIVE_MODE,0)=0 AND OLD.estatus <> 'cancelada' AND NEW.estatus = 'cancelada' THEN
    INSERT INTO log_cancelaciones (tipo, venta_id, usuario_id, motivo, total_anterior, fecha)
    VALUES ('venta', NEW.id, NEW.usuario_id, NEW.observacion, OLD.total, NOW());
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_detalles`
--

CREATE TABLE `venta_detalles` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED,
  `insumos_descargados` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `entregado_hr` datetime DEFAULT NULL,
  `estado_producto` enum('pendiente','en_preparacion','listo','entregado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `venta_detalles`
--

INSERT INTO `venta_detalles` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `insumos_descargados`, `created_at`, `entregado_hr`, `estado_producto`, `observaciones`) VALUES
(240, 125, 17, 4, 119.00, 1, '2025-08-27 18:55:53', '2025-08-27 18:56:03', 'entregado', NULL),
(245, 127, 136, 1, 99.00, 1, '2025-08-27 19:14:36', '2025-08-27 19:14:47', 'entregado', NULL),
(246, 127, 15, 1, 115.00, 1, '2025-08-27 19:14:36', '2025-08-27 19:14:49', 'entregado', NULL),
(247, 127, 93, 1, 145.00, 1, '2025-08-27 19:14:36', '2025-08-27 19:14:50', 'entregado', NULL),
(248, 128, 62, 1, 49.00, 1, '2025-08-27 19:25:29', '2025-08-27 19:26:22', 'entregado', NULL),
(249, 129, 77, 1, 10.00, 1, '2025-08-27 19:30:40', '2025-08-27 19:30:46', 'entregado', NULL),
(250, 130, 15, 1, 115.00, 1, '2025-08-28 16:59:24', '2025-08-28 16:59:47', 'entregado', NULL);

--
-- Disparadores `venta_detalles`
--
DELIMITER $$
CREATE TRIGGER `tr_log_estado_producto` AFTER UPDATE ON `venta_detalles` FOR EACH ROW BEGIN
  IF NOT (OLD.estado_producto <=> NEW.estado_producto) THEN
    INSERT INTO venta_detalles_log (venta_detalle_id, estado_anterior, estado_nuevo)
    VALUES (OLD.id, OLD.estado_producto, NEW.estado_producto);
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_log_cancel_detalle` BEFORE DELETE ON `venta_detalles` FOR EACH ROW BEGIN
  IF COALESCE(@ARCHIVE_MODE,0)=0 THEN
    INSERT INTO log_cancelaciones
      (tipo, venta_id, venta_detalle_id, usuario_id, motivo, subtotal_detalle, fecha)
    VALUES
      ('detalle', OLD.venta_id, OLD.id, @usuario_id, COALESCE(@motivo_cancelacion,'Eliminación de producto'), OLD.subtotal, NOW());
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_respaldo_venta_detalles` BEFORE DELETE ON `venta_detalles` FOR EACH ROW BEGIN
  IF COALESCE(@ARCHIVE_MODE,0)=0 THEN
    INSERT INTO venta_detalles_cancelados (
      venta_detalle_id_original, venta_id, producto_id, cantidad, precio_unitario,
      insumos_descargados, created_at, entregado_hr, estado_producto, observaciones,
      cancelado_por, motivo
    ) VALUES (
      OLD.id, OLD.venta_id, OLD.producto_id, OLD.cantidad, OLD.precio_unitario,
      OLD.insumos_descargados, OLD.created_at, OLD.entregado_hr, OLD.estado_producto, OLD.observaciones,
      @usuario_id, @motivo_cancelacion
    );
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_detalles_cancelados`
--

CREATE TABLE `venta_detalles_cancelados` (
  `id` int(11) NOT NULL,
  `venta_detalle_id_original` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `insumos_descargados` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `entregado_hr` datetime DEFAULT NULL,
  `estado_producto` enum('pendiente','en_preparacion','listo','entregado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `cancelado_por` int(11) DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT current_timestamp(),
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `venta_detalles_cancelados`
--

INSERT INTO `venta_detalles_cancelados` (`id`, `venta_detalle_id_original`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `insumos_descargados`, `created_at`, `entregado_hr`, `estado_producto`, `observaciones`, `cancelado_por`, `fecha_cancelacion`, `motivo`) VALUES
(4, 238, 124, 77, 1, 10.00, 0, '2025-08-27 16:09:28', NULL, 'pendiente', NULL, NULL, '2025-08-27 16:21:49', NULL),
(5, 239, 124, 72, 1, 35.00, 0, '2025-08-27 16:09:28', NULL, 'pendiente', NULL, NULL, '2025-08-27 16:21:49', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_detalles_log`
--

CREATE TABLE `venta_detalles_log` (
  `id` int(11) NOT NULL,
  `venta_detalle_id` int(11) NOT NULL,
  `estado_anterior` enum('pendiente','en_preparacion','listo','entregado') DEFAULT NULL,
  `estado_nuevo` enum('pendiente','en_preparacion','listo','entregado') NOT NULL,
  `cambiado_en` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `venta_detalles_log`
--

INSERT INTO `venta_detalles_log` (`id`, `venta_detalle_id`, `estado_anterior`, `estado_nuevo`, `cambiado_en`) VALUES
(229, 240, 'pendiente', 'en_preparacion', '2025-08-27 18:56:01'),
(230, 240, 'en_preparacion', 'listo', '2025-08-27 18:56:02'),
(231, 240, 'listo', 'entregado', '2025-08-27 18:56:03'),
(244, 245, 'pendiente', 'en_preparacion', '2025-08-27 19:14:42'),
(245, 246, 'pendiente', 'en_preparacion', '2025-08-27 19:14:43'),
(246, 247, 'pendiente', 'en_preparacion', '2025-08-27 19:14:44'),
(247, 245, 'en_preparacion', 'listo', '2025-08-27 19:14:44'),
(248, 245, 'listo', 'entregado', '2025-08-27 19:14:47'),
(249, 246, 'en_preparacion', 'listo', '2025-08-27 19:14:48'),
(250, 247, 'en_preparacion', 'listo', '2025-08-27 19:14:49'),
(251, 246, 'listo', 'entregado', '2025-08-27 19:14:49'),
(252, 247, 'listo', 'entregado', '2025-08-27 19:14:50'),
(253, 248, 'pendiente', 'en_preparacion', '2025-08-27 19:26:20'),
(254, 248, 'en_preparacion', 'listo', '2025-08-27 19:26:21'),
(255, 248, 'listo', 'entregado', '2025-08-27 19:26:22'),
(256, 249, 'pendiente', 'en_preparacion', '2025-08-27 19:30:45'),
(257, 249, 'en_preparacion', 'listo', '2025-08-27 19:30:45'),
(258, 249, 'listo', 'entregado', '2025-08-27 19:30:46'),
(259, 250, 'pendiente', 'en_preparacion', '2025-08-28 16:59:43'),
(260, 250, 'en_preparacion', 'listo', '2025-08-28 16:59:46'),
(261, 250, 'listo', 'entregado', '2025-08-28 16:59:47');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_facturas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_facturas` (
`factura_id` int(11)
,`folio` varchar(50)
,`uuid` varchar(64)
,`subtotal` decimal(10,2)
,`impuestos` decimal(10,2)
,`total` decimal(10,2)
,`fecha_emision` datetime
,`estado` enum('generada','cancelada')
,`ticket_id` int(11)
,`ticket_folio` int(11)
,`ticket_total` decimal(10,2)
,`cliente_id` int(11)
,`rfc` varchar(20)
,`razon_social` varchar(200)
,`correo` varchar(150)
,`telefono` varchar(30)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_factura_detalles`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_factura_detalles` (
`id` int(11)
,`factura_id` int(11)
,`ticket_detalle_id` int(11)
,`producto_id` int(11)
,`descripcion` varchar(255)
,`cantidad` int(11)
,`precio_unitario` decimal(10,2)
,`importe` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_productos_mas_vendidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_productos_mas_vendidos` (
`producto_id` int(11)
,`nombre` varchar(100)
,`total_vendidos` decimal(32,0)
,`total_ingresos` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_resumen_cortes`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_resumen_cortes` (
`corte_id` int(11)
,`usuario_id` int(11)
,`fecha_inicio` datetime
,`fecha_fin` datetime
,`fondo_inicial` decimal(10,2)
,`total_corte` decimal(10,2)
,`total_ventas` decimal(32,2)
,`total_propinas` decimal(12,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_resumen_pagos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_resumen_pagos` (
`corte_id` int(11)
,`tipo_pago` enum('efectivo','boucher','cheque')
,`total_productos` decimal(35,2)
,`total_propinas` decimal(12,2)
,`total_con_propina` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ventas_diarias`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ventas_diarias` (
`fecha` date
,`cantidad_ventas` bigint(21)
,`total_productos` decimal(35,2)
,`total_propinas` decimal(12,2)
,`total_general` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_consumo_insumos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_consumo_insumos` (
`venta_id` int(11)
,`insumo_id` int(11)
,`insumo` varchar(100)
,`unidad` varchar(20)
,`total_consumido` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_corte_resumen`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_corte_resumen` (
`corte_id` int(11)
,`cajero` varchar(100)
,`fecha_inicio` datetime
,`fecha_fin` datetime
,`total` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_kpi_producto`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_kpi_producto` (
`producto_id` int(11)
,`total_ordenes` bigint(21)
,`avg_pendiente_a_preparacion` decimal(21,0)
,`avg_preparacion_a_listo` decimal(21,0)
,`avg_listo_a_entregado` decimal(21,0)
,`avg_total_servicio` decimal(21,0)
,`max_total_servicio` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_kpi_venta`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_kpi_venta` (
`venta_id` int(11)
,`productos` bigint(21)
,`avg_servicio_por_producto` decimal(21,0)
,`max_servicio_producto` bigint(21)
,`total_servicio_venta` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_productos_recetas_agrupado`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_productos_recetas_agrupado` (
`producto_id` int(11)
,`producto` varchar(100)
,`receta` mediumtext
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_tiempos_producto`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_tiempos_producto` (
`venta_detalle_id` int(11)
,`venta_id` int(11)
,`producto_id` int(11)
,`ts_pendiente` datetime
,`ts_preparacion` datetime
,`ts_listo` datetime
,`ts_entregado` datetime
,`segs_pendiente_a_preparacion` bigint(21)
,`segs_preparacion_a_listo` bigint(21)
,`segs_listo_a_entregado` bigint(21)
,`segs_total_servicio` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_ventas_detalladas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_ventas_detalladas` (
`venta_id` int(11)
,`fecha` datetime
,`total` decimal(10,2)
,`estatus` enum('activa','cerrada','cancelada')
,`usuario` varchar(100)
,`mesa` varchar(50)
,`repartidor` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_facturas`
--
DROP TABLE IF EXISTS `vista_facturas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_facturas`  AS SELECT `f`.`id` AS `factura_id`, `f`.`folio` AS `folio`, `f`.`uuid` AS `uuid`, `f`.`subtotal` AS `subtotal`, `f`.`impuestos` AS `impuestos`, `f`.`total` AS `total`, `f`.`fecha_emision` AS `fecha_emision`, `f`.`estado` AS `estado`, `f`.`ticket_id` AS `ticket_id`, `t`.`folio` AS `ticket_folio`, `t`.`total` AS `ticket_total`, `c`.`id` AS `cliente_id`, `c`.`rfc` AS `rfc`, `c`.`razon_social` AS `razon_social`, `c`.`correo` AS `correo`, `c`.`telefono` AS `telefono` FROM ((`facturas` `f` join `tickets` `t` on(`t`.`id` = `f`.`ticket_id`)) join `clientes_facturacion` `c` on(`c`.`id` = `f`.`cliente_id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_factura_detalles`
--
DROP TABLE IF EXISTS `vista_factura_detalles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_factura_detalles`  AS SELECT `fd`.`id` AS `id`, `fd`.`factura_id` AS `factura_id`, `fd`.`ticket_detalle_id` AS `ticket_detalle_id`, `fd`.`producto_id` AS `producto_id`, coalesce(`fd`.`descripcion`,`p`.`nombre`) AS `descripcion`, `fd`.`cantidad` AS `cantidad`, `fd`.`precio_unitario` AS `precio_unitario`, `fd`.`importe` AS `importe` FROM (`factura_detalles` `fd` left join `productos` `p` on(`p`.`id` = `fd`.`producto_id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_productos_mas_vendidos`
--
DROP TABLE IF EXISTS `vista_productos_mas_vendidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_productos_mas_vendidos`  AS SELECT `vd`.`producto_id` AS `producto_id`, `p`.`nombre` AS `nombre`, sum(`vd`.`cantidad`) AS `total_vendidos`, sum(`vd`.`cantidad` * `vd`.`precio_unitario`) AS `total_ingresos` FROM (`venta_detalles` `vd` join `productos` `p` on(`vd`.`producto_id` = `p`.`id`)) GROUP BY `vd`.`producto_id`, `p`.`nombre` ORDER BY sum(`vd`.`cantidad`) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_cortes`
--
DROP TABLE IF EXISTS `vista_resumen_cortes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_cortes`  AS SELECT `c`.`id` AS `corte_id`, `c`.`usuario_id` AS `usuario_id`, `c`.`fecha_inicio` AS `fecha_inicio`, `c`.`fecha_fin` AS `fecha_fin`, `c`.`fondo_inicial` AS `fondo_inicial`, `c`.`total` AS `total_corte`, coalesce(sum(`t`.`total`),0) AS `total_ventas`, coalesce(`v`.`propina_efectivo` + `v`.`propina_cheque` + `v`.`propina_tarjeta`,0) AS `total_propinas` FROM ((`corte_caja` `c` left join `ventas` `v` on(`v`.`corte_id` = `c`.`id` and `v`.`estatus` = 'cerrada')) left join `tickets` `t` on(`t`.`venta_id` = `v`.`id`)) GROUP BY `c`.`id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_pagos`
--
DROP TABLE IF EXISTS `vista_resumen_pagos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_pagos`  AS SELECT `v`.`corte_id` AS `corte_id`, `t`.`tipo_pago` AS `tipo_pago`, sum(`t`.`total` - (`v`.`propina_efectivo` + `v`.`propina_cheque` + `v`.`propina_tarjeta`)) AS `total_productos`, `v`.`propina_efectivo`+ `v`.`propina_cheque` + `v`.`propina_tarjeta` AS `total_propinas`, sum(`t`.`total`) AS `total_con_propina` FROM (`tickets` `t` join `ventas` `v` on(`t`.`venta_id` = `v`.`id`)) WHERE `v`.`estatus` = 'cerrada' GROUP BY `v`.`corte_id`, `t`.`tipo_pago` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas_diarias`
--
DROP TABLE IF EXISTS `vista_ventas_diarias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ventas_diarias`  AS SELECT cast(`t`.`fecha` as date) AS `fecha`, count(0) AS `cantidad_ventas`, sum(`t`.`total` - (`v`.`propina_efectivo` + `v`.`propina_cheque` + `v`.`propina_tarjeta`)) AS `total_productos`, `v`.`propina_efectivo`+ `v`.`propina_cheque` + `v`.`propina_tarjeta` AS `total_propinas`, sum(`t`.`total`) AS `total_general` FROM (`tickets` `t` join `ventas` `v` on(`t`.`venta_id` = `v`.`id`)) WHERE `v`.`estatus` = 'cerrada' GROUP BY cast(`t`.`fecha` as date) ORDER BY cast(`t`.`fecha` as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_consumo_insumos`
--
DROP TABLE IF EXISTS `vw_consumo_insumos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_consumo_insumos`  AS SELECT `vd`.`venta_id` AS `venta_id`, `r`.`insumo_id` AS `insumo_id`, `i`.`nombre` AS `insumo`, `i`.`unidad` AS `unidad`, sum(`r`.`cantidad` * `vd`.`cantidad`) AS `total_consumido` FROM ((`venta_detalles` `vd` join `recetas` `r` on(`vd`.`producto_id` = `r`.`producto_id`)) join `insumos` `i` on(`r`.`insumo_id` = `i`.`id`)) GROUP BY `vd`.`venta_id`, `r`.`insumo_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_corte_resumen`
--
DROP TABLE IF EXISTS `vw_corte_resumen`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_corte_resumen`  AS SELECT `c`.`id` AS `corte_id`, `u`.`nombre` AS `cajero`, `c`.`fecha_inicio` AS `fecha_inicio`, `c`.`fecha_fin` AS `fecha_fin`, `c`.`total` AS `total` FROM (`corte_caja` `c` join `usuarios` `u` on(`c`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_kpi_producto`
--
DROP TABLE IF EXISTS `vw_kpi_producto`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_kpi_producto`  AS SELECT `vp`.`producto_id` AS `producto_id`, count(0) AS `total_ordenes`, round(avg(`vp`.`segs_pendiente_a_preparacion`),0) AS `avg_pendiente_a_preparacion`, round(avg(`vp`.`segs_preparacion_a_listo`),0) AS `avg_preparacion_a_listo`, round(avg(`vp`.`segs_listo_a_entregado`),0) AS `avg_listo_a_entregado`, round(avg(`vp`.`segs_total_servicio`),0) AS `avg_total_servicio`, max(`vp`.`segs_total_servicio`) AS `max_total_servicio` FROM `vw_tiempos_producto` AS `vp` GROUP BY `vp`.`producto_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_kpi_venta`
--
DROP TABLE IF EXISTS `vw_kpi_venta`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_kpi_venta`  AS SELECT `vp`.`venta_id` AS `venta_id`, count(distinct `vp`.`producto_id`) AS `productos`, round(avg(`vp`.`segs_total_servicio`),0) AS `avg_servicio_por_producto`, max(`vp`.`segs_total_servicio`) AS `max_servicio_producto`, timestampdiff(SECOND,min(`vp`.`ts_pendiente`),max(`vp`.`ts_entregado`)) AS `total_servicio_venta` FROM `vw_tiempos_producto` AS `vp` GROUP BY `vp`.`venta_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_productos_recetas_agrupado`
--
DROP TABLE IF EXISTS `vw_productos_recetas_agrupado`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_productos_recetas_agrupado`  AS SELECT `p`.`id` AS `producto_id`, `p`.`nombre` AS `producto`, group_concat(concat(`i`.`nombre`,' (',`r`.`cantidad`,' ',`i`.`unidad`,')') order by `i`.`nombre` ASC separator ' | ') AS `receta` FROM ((`recetas` `r` join `productos` `p` on(`p`.`id` = `r`.`producto_id`)) join `insumos` `i` on(`i`.`id` = `r`.`insumo_id`)) GROUP BY `p`.`id`, `p`.`nombre` ORDER BY `p`.`nombre` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_tiempos_producto`
--
DROP TABLE IF EXISTS `vw_tiempos_producto`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_tiempos_producto`  AS SELECT `vd`.`id` AS `venta_detalle_id`, `vd`.`venta_id` AS `venta_id`, `vd`.`producto_id` AS `producto_id`, coalesce(min(case when `vdl`.`estado_nuevo` = 'pendiente' then `vdl`.`cambiado_en` end),`vd`.`created_at`) AS `ts_pendiente`, min(case when `vdl`.`estado_nuevo` = 'en_preparacion' then `vdl`.`cambiado_en` end) AS `ts_preparacion`, min(case when `vdl`.`estado_nuevo` = 'listo' then `vdl`.`cambiado_en` end) AS `ts_listo`, min(case when `vdl`.`estado_nuevo` = 'entregado' then `vdl`.`cambiado_en` end) AS `ts_entregado`, timestampdiff(SECOND,coalesce(min(case when `vdl`.`estado_nuevo` = 'pendiente' then `vdl`.`cambiado_en` end),`vd`.`created_at`),min(case when `vdl`.`estado_nuevo` = 'en_preparacion' then `vdl`.`cambiado_en` end)) AS `segs_pendiente_a_preparacion`, timestampdiff(SECOND,min(case when `vdl`.`estado_nuevo` = 'en_preparacion' then `vdl`.`cambiado_en` end),min(case when `vdl`.`estado_nuevo` = 'listo' then `vdl`.`cambiado_en` end)) AS `segs_preparacion_a_listo`, timestampdiff(SECOND,min(case when `vdl`.`estado_nuevo` = 'listo' then `vdl`.`cambiado_en` end),min(case when `vdl`.`estado_nuevo` = 'entregado' then `vdl`.`cambiado_en` end)) AS `segs_listo_a_entregado`, timestampdiff(SECOND,coalesce(min(case when `vdl`.`estado_nuevo` = 'pendiente' then `vdl`.`cambiado_en` end),`vd`.`created_at`),min(case when `vdl`.`estado_nuevo` = 'entregado' then `vdl`.`cambiado_en` end)) AS `segs_total_servicio` FROM (`venta_detalles` `vd` left join `venta_detalles_log` `vdl` on(`vdl`.`venta_detalle_id` = `vd`.`id`)) GROUP BY `vd`.`id`, `vd`.`venta_id`, `vd`.`producto_id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_ventas_detalladas`
--
DROP TABLE IF EXISTS `vw_ventas_detalladas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_ventas_detalladas`  AS SELECT `v`.`id` AS `venta_id`, `v`.`fecha` AS `fecha`, `v`.`total` AS `total`, `v`.`estatus` AS `estatus`, `u`.`nombre` AS `usuario`, `m`.`nombre` AS `mesa`, `r`.`nombre` AS `repartidor` FROM (((`ventas` `v` left join `usuarios` `u` on(`v`.`usuario_id` = `u`.`id`)) left join `mesas` `m` on(`v`.`mesa_id` = `m`.`id`)) left join `repartidores` `r` on(`v`.`repartidor_id` = `r`.`id`)) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alineacion`
--
ALTER TABLE `alineacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `catalogo_areas`
--
ALTER TABLE `catalogo_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_bancos`
--
ALTER TABLE `catalogo_bancos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_categorias`
--
ALTER TABLE `catalogo_categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_denominaciones`
--
ALTER TABLE `catalogo_denominaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_folios`
--
ALTER TABLE `catalogo_folios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_tarjetas`
--
ALTER TABLE `catalogo_tarjetas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes_facturacion`
--
ALTER TABLE `clientes_facturacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfc` (`rfc`);

--
-- Indices de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_padre` (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `corte_caja_historial`
--
ALTER TABLE `corte_caja_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `corte_id` (`corte_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `desglose_corte`
--
ALTER TABLE `desglose_corte`
  ADD PRIMARY KEY (`id`),
  ADD KEY `corte_id` (`corte_id`),
  ADD KEY `fk_denominacion` (`denominacion_id`);

--
-- Indices de la tabla `entradas_detalle`
--
ALTER TABLE `entradas_detalle`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entrada_id` (`entrada_id`),
  ADD KEY `fk_entrada_detalle_insumo` (`insumo_id`);

--
-- Indices de la tabla `entradas_insumo`
--
ALTER TABLE `entradas_insumo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `fk_entrada_usuario` (`usuario_id`);

--
-- Indices de la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fact_ticket` (`ticket_id`),
  ADD KEY `idx_fact_cliente` (`cliente_id`);

--
-- Indices de la tabla `factura_detalles`
--
ALTER TABLE `factura_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fd_fact` (`factura_id`);

--
-- Indices de la tabla `fondo`
--
ALTER TABLE `fondo`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_horario_serie` (`serie_id`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `logs_accion`
--
ALTER TABLE `logs_accion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `log_asignaciones_mesas`
--
ALTER TABLE `log_asignaciones_mesas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mesa_id` (`mesa_id`),
  ADD KEY `mesero_anterior_id` (`mesero_anterior_id`),
  ADD KEY `mesero_nuevo_id` (`mesero_nuevo_id`),
  ADD KEY `usuario_que_asigna_id` (`usuario_que_asigna_id`);

--
-- Indices de la tabla `log_cancelaciones`
--
ALTER TABLE `log_cancelaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `log_mesas`
--
ALTER TABLE `log_mesas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mesa_id` (`mesa_id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `menu_dia`
--
ALTER TABLE `menu_dia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_mesa_area` (`area_id`),
  ADD KEY `fk_mesas_alineacion` (`alineacion_id`);

--
-- Indices de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `corte_id` (`corte_id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `movimientos_insumos`
--
ALTER TABLE `movimientos_insumos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ofertas_dia`
--
ALTER TABLE `ofertas_dia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_productos_categoria` (`categoria_id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `qrs_insumo`
--
ALTER TABLE `qrs_insumo`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `producto_id` (`producto_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indices de la tabla `repartidores`
--
ALTER TABLE `repartidores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_tickets_sede` (`sede_id`),
  ADD KEY `fk_ticket_tarjeta_marca` (`tarjeta_marca_id`),
  ADD KEY `fk_ticket_tarjeta_banco` (`tarjeta_banco_id`),
  ADD KEY `fk_ticket_cheque_banco` (`cheque_banco_id`);

--
-- Indices de la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- Indices de la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`,`ruta_id`),
  ADD KEY `ruta_id` (`ruta_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mesa_id` (`mesa_id`),
  ADD KEY `repartidor_id` (`repartidor_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_corte` (`corte_id`),
  ADD KEY `cajero_id` (`cajero_id`),
  ADD KEY `fk_ventas_sede` (`sede_id`);

--
-- Indices de la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `venta_detalles_cancelados`
--
ALTER TABLE `venta_detalles_cancelados`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `venta_detalles_log`
--
ALTER TABLE `venta_detalles_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_detalle_id` (`venta_detalle_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alineacion`
--
ALTER TABLE `alineacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `catalogo_areas`
--
ALTER TABLE `catalogo_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `catalogo_bancos`
--
ALTER TABLE `catalogo_bancos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `catalogo_categorias`
--
ALTER TABLE `catalogo_categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `catalogo_denominaciones`
--
ALTER TABLE `catalogo_denominaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `catalogo_folios`
--
ALTER TABLE `catalogo_folios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `catalogo_tarjetas`
--
ALTER TABLE `catalogo_tarjetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `clientes_facturacion`
--
ALTER TABLE `clientes_facturacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT de la tabla `corte_caja_historial`
--
ALTER TABLE `corte_caja_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `desglose_corte`
--
ALTER TABLE `desglose_corte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=252;

--
-- AUTO_INCREMENT de la tabla `entradas_detalle`
--
ALTER TABLE `entradas_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entradas_insumo`
--
ALTER TABLE `entradas_insumo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `facturas`
--
ALTER TABLE `facturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `factura_detalles`
--
ALTER TABLE `factura_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT de la tabla `logs_accion`
--
ALTER TABLE `logs_accion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=693;

--
-- AUTO_INCREMENT de la tabla `log_asignaciones_mesas`
--
ALTER TABLE `log_asignaciones_mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `log_cancelaciones`
--
ALTER TABLE `log_cancelaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `log_mesas`
--
ALTER TABLE `log_mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de la tabla `menu_dia`
--
ALTER TABLE `menu_dia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `movimientos_insumos`
--
ALTER TABLE `movimientos_insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT de la tabla `ofertas_dia`
--
ALTER TABLE `ofertas_dia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `qrs_insumo`
--
ALTER TABLE `qrs_insumo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `recetas`
--
ALTER TABLE `recetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=828;

--
-- AUTO_INCREMENT de la tabla `repartidores`
--
ALTER TABLE `repartidores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT de la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=217;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT de la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=251;

--
-- AUTO_INCREMENT de la tabla `venta_detalles_cancelados`
--
ALTER TABLE `venta_detalles_cancelados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `venta_detalles_log`
--
ALTER TABLE `venta_detalles_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=262;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  ADD CONSTRAINT `corte_caja_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `corte_caja_historial`
--
ALTER TABLE `corte_caja_historial`
  ADD CONSTRAINT `corte_caja_historial_ibfk_1` FOREIGN KEY (`corte_id`) REFERENCES `corte_caja` (`id`),
  ADD CONSTRAINT `corte_caja_historial_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `desglose_corte`
--
ALTER TABLE `desglose_corte`
  ADD CONSTRAINT `desglose_corte_ibfk_1` FOREIGN KEY (`corte_id`) REFERENCES `corte_caja` (`id`),
  ADD CONSTRAINT `fk_denominacion` FOREIGN KEY (`denominacion_id`) REFERENCES `catalogo_denominaciones` (`id`);

--
-- Filtros para la tabla `entradas_detalle`
--
ALTER TABLE `entradas_detalle`
  ADD CONSTRAINT `entradas_detalle_ibfk_1` FOREIGN KEY (`entrada_id`) REFERENCES `entradas_insumo` (`id`),
  ADD CONSTRAINT `fk_entrada_detalle_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`);

--
-- Filtros para la tabla `entradas_insumo`
--
ALTER TABLE `entradas_insumo`
  ADD CONSTRAINT `entradas_insumo_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  ADD CONSTRAINT `fk_entrada_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `fk_fact_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes_facturacion` (`id`),
  ADD CONSTRAINT `fk_fact_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`);

--
-- Filtros para la tabla `factura_detalles`
--
ALTER TABLE `factura_detalles`
  ADD CONSTRAINT `fk_fd_fact` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`);

--
-- Filtros para la tabla `fondo`
--
ALTER TABLE `fondo`
  ADD CONSTRAINT `fondo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `fk_horario_serie` FOREIGN KEY (`serie_id`) REFERENCES `catalogo_folios` (`id`);

--
-- Filtros para la tabla `log_asignaciones_mesas`
--
ALTER TABLE `log_asignaciones_mesas`
  ADD CONSTRAINT `log_asignaciones_mesas_ibfk_1` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`),
  ADD CONSTRAINT `log_asignaciones_mesas_ibfk_2` FOREIGN KEY (`mesero_anterior_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `log_asignaciones_mesas_ibfk_3` FOREIGN KEY (`mesero_nuevo_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `log_asignaciones_mesas_ibfk_4` FOREIGN KEY (`usuario_que_asigna_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `log_mesas`
--
ALTER TABLE `log_mesas`
  ADD CONSTRAINT `log_mesas_ibfk_1` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`),
  ADD CONSTRAINT `log_mesas_ibfk_2` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `log_mesas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `mesas`
--
ALTER TABLE `mesas`
  ADD CONSTRAINT `fk_mesa_area` FOREIGN KEY (`area_id`) REFERENCES `catalogo_areas` (`id`),
  ADD CONSTRAINT `fk_mesas_alineacion` FOREIGN KEY (`alineacion_id`) REFERENCES `alineacion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `mesas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  ADD CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`corte_id`) REFERENCES `corte_caja` (`id`),
  ADD CONSTRAINT `movimientos_caja_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `catalogo_categorias` (`id`);

--
-- Filtros para la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`),
  ADD CONSTRAINT `recetas_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`);

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_ticket_cheque_banco` FOREIGN KEY (`cheque_banco_id`) REFERENCES `catalogo_bancos` (`id`),
  ADD CONSTRAINT `fk_ticket_tarjeta_banco` FOREIGN KEY (`tarjeta_banco_id`) REFERENCES `catalogo_bancos` (`id`),
  ADD CONSTRAINT `fk_ticket_tarjeta_marca` FOREIGN KEY (`tarjeta_marca_id`) REFERENCES `catalogo_tarjetas` (`id`),
  ADD CONSTRAINT `fk_tickets_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  ADD CONSTRAINT `ticket_detalles_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  ADD CONSTRAINT `ticket_detalles_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  ADD CONSTRAINT `usuario_ruta_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_ruta_ibfk_2` FOREIGN KEY (`ruta_id`) REFERENCES `rutas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_corte` FOREIGN KEY (`corte_id`) REFERENCES `corte_caja` (`id`),
  ADD CONSTRAINT `fk_venta_corte` FOREIGN KEY (`corte_id`) REFERENCES `corte_caja` (`id`),
  ADD CONSTRAINT `fk_ventas_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`mesa_id`) REFERENCES `mesas` (`id`),
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`repartidor_id`) REFERENCES `repartidores` (`id`),
  ADD CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `ventas_ibfk_4` FOREIGN KEY (`cajero_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  ADD CONSTRAINT `venta_detalles_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `venta_detalles_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `venta_detalles_log`
--
ALTER TABLE `venta_detalles_log`
  ADD CONSTRAINT `venta_detalles_log_ibfk_1` FOREIGN KEY (`venta_detalle_id`) REFERENCES `venta_detalles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
