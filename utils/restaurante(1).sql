-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-08-2025 a las 22:33:07
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
CREATE DATABASE IF NOT EXISTS `restaurante` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `restaurante`;

DELIMITER $$
--
-- Procedimientos
--
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
(2, 'Serie Domicilio', 2008);

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
(51, 1, '2025-08-19 18:10:17', 2000, 2003, 3, '2025-08-20 07:23:18', 4000.00, '', 4000.00),
(52, 1, '2025-08-20 15:31:53', 2003, 2006, 3, '2025-08-20 16:47:26', 4000.00, '', 4000.00),
(53, 1, '2025-08-21 01:21:53', 2006, 2008, 2, '2025-08-21 14:17:16', 4000.00, '', 4000.00);

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
(208, 51, 1.00, 442, 'boucher', 12),
(209, 51, 1.00, 168, 'cheque', 13),
(210, 51, 1.00, 442, 'efectivo', 12),
(211, 51, 1.00, 168, 'efectivo', 13),
(212, 51, 20.00, 1, 'efectivo', 6),
(213, 51, 500.00, 5, 'efectivo', 10),
(214, 51, 1000.00, 2, 'efectivo', 11),
(215, 51, 442.00, 1, 'boucher', NULL),
(216, 51, 168.00, 1, 'cheque', NULL),
(217, 52, 20.00, 2, 'efectivo', 6),
(218, 52, 50.00, 1, 'efectivo', 7),
(219, 52, 200.00, 2, 'efectivo', 9),
(220, 52, 1000.00, 4, 'efectivo', 11),
(221, 53, 1.00, 1, 'efectivo', 2),
(222, 53, 10.00, 1, 'efectivo', 5),
(223, 53, 200.00, 1, 'efectivo', 9),
(224, 53, 1000.00, 4, 'efectivo', 11);

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
(1, 4000.00);

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
(1, 'Arroz para sushi', 'gramos', 3000.00, 'por_receta', 'ins_68717301313ad.jpg'),
(2, 'Alga Nori', 'piezas', 3000.00, 'por_receta', 'ins_6871716a72681.jpg'),
(3, 'Salmón fresco', 'gramos', 3000.00, 'por_receta', 'ins_6871777fa2c56.png'),
(4, 'Refresco en lata', 'piezas', 2993.00, 'unidad_completa', 'ins_6871731d075cb.webp'),
(7, 'Surimi', 'pieza', 3000.00, 'uso_general', 'ins_688a521dcd583.jpg'),
(8, 'Tocino', 'gramos', 3000.00, 'uso_general', 'ins_688a4dc84c002.jpg'),
(9, 'Pollo', 'gramos', 3000.00, 'desempaquetado', 'ins_688a4e4bd5999.jpg'),
(10, 'Camarón', 'pieza', 3000.00, 'desempaquetado', 'ins_688a4f5c873c6.jpg'),
(11, 'Queso chihuahua', 'gramos', 3000.00, 'unidad_completa', 'ins_688a4feca9865.jpg'),
(12, 'Philadelphia', 'gramos', 3000.00, 'uso_general', 'ins_688a504f9cb40.jpg'),
(13, 'Arroz blanco', 'gramos', 3000.00, 'por_receta', 'ins_689f82d674c65.jpg'),
(14, 'Carne de res', 'gramos', 3000.00, 'uso_general', 'ins_688a528d1261a.jpg'),
(15, 'Queso americano', 'gramos', 3000.00, 'uso_general', 'ins_688a53246c1c2.jpg'),
(16, 'Ajonjolí', 'gramos', 3000.00, 'uso_general', 'ins_689f824a23343.jpg'),
(17, 'Panko', 'gramos', 3000.00, 'por_receta', 'ins_688a53da64b5f.jpg'),
(18, 'Salsa tampico', 'mililitros', 3000.00, 'no_controlado', 'ins_688a54cf1872b.jpg'),
(19, 'Anguila', 'gramos', 3000.00, 'por_receta', 'ins_689f828638aa9.jpg'),
(20, 'salsa bbq', 'mililitros', 3000.00, 'no_controlado', 'ins_688a557431fce.jpg'),
(21, 'Chile serrano', 'gramos', 3000.00, 'uso_general', 'ins_688a55c66f09d.jpg'),
(22, 'Chile morrón', 'gramos', 3000.00, 'por_receta', 'ins_688a5616e8f25.jpg'),
(23, 'Kanikama', 'gramos', 3000.00, 'por_receta', 'ins_688a5669e24a8.jpg'),
(24, 'Aguacate', 'gramos', 3000.00, 'por_receta', 'ins_689f8254c2e71.jpg'),
(25, 'Dedos de queso', 'pieza', 3000.00, 'unidad_completa', 'ins_688a56fda3221.jpg'),
(26, 'Mango', 'gramos', 3000.00, 'por_receta', 'ins_688a573c762f4.jpg'),
(27, 'Tostadas', 'pieza', 3000.00, 'uso_general', 'ins_688a57a499b35.jpg'),
(28, 'Papa', 'gramos', 3000.00, 'por_receta', 'ins_688a580061ffd.jpg'),
(29, 'Cebolla morada', 'gramos', 3000.00, 'por_receta', 'ins_688a5858752a0.jpg'),
(30, 'Salsa de soya', 'mililitros', 3000.00, 'no_controlado', 'ins_688a58cc6cb6c.jpg'),
(31, 'Naranja', 'gramos', 3000.00, 'por_receta', 'ins_688a590bca275.jpg'),
(32, 'Chile caribe', 'gramos', 3000.00, 'por_receta', 'ins_688a59836c32e.jpg'),
(33, 'Pulpo', 'gramos', 3000.00, 'por_receta', 'ins_688a59c9a1d0b.jpg'),
(34, 'Zanahoria', 'gramos', 3000.00, 'por_receta', 'ins_688a5a0a3a959.jpg'),
(35, 'Apio', 'gramos', 3000.00, 'por_receta', 'ins_688a5a52af990.jpg'),
(36, 'Pepino', 'gramos', 3000.00, 'uso_general', 'ins_688a5aa0cbaf5.jpg'),
(37, 'Masago', 'gramos', 3000.00, 'por_receta', 'ins_688a5b3f0dca6.jpg'),
(38, 'Nuez de la india', 'gramos', 3000.00, 'por_receta', 'ins_688a5be531e11.jpg'),
(39, 'Cátsup', 'gramos', 3000.00, 'por_receta', 'ins_688a5c657eb83.jpg'),
(40, 'Atún', 'gramos', 3000.00, 'por_receta', 'ins_688a5ce18adc5.jpg'),
(41, 'Callo', 'gramos', 3000.00, 'por_receta', 'ins_688a5d28de8a5.jpg'),
(42, 'Calabacin', 'gramos', 3000.00, 'por_receta', 'ins_688a5d6b2bca1.jpg'),
(43, 'Fideo chino transparente', 'gramos', 3000.00, 'por_receta', 'ins_688a5dd3b406d.jpg'),
(44, 'Brócoli', 'gramos', 3000.00, 'por_receta', 'ins_688a5e2736870.jpg'),
(45, 'Chile de árbol', 'pieza', 3000.00, 'por_receta', 'ins_688a5e6f08ccd.jpg'),
(46, 'Pasta udon', 'gramos', 3000.00, 'por_receta', 'ins_688a5eb627f38.jpg'),
(47, 'Huevo', 'pieza', 3000.00, 'por_receta', 'ins_688a5ef9b575e.jpg'),
(48, 'Cerdo', 'gramos', 3000.00, 'por_receta', 'ins_688a5f3915f5e.jpg'),
(49, 'Masa para gyozas', 'pieza', 3000.00, 'por_receta', 'ins_688a5fae2e7f1.jpg'),
(50, 'Naruto', 'gramos', 3000.00, 'por_receta', 'ins_688a5ff57f62d.jpg'),
(51, 'Atún ahumado', 'gramos', 3000.00, 'por_receta', ''),
(52, 'Cacahuate con salsa (salado)', 'gramos', 3000.00, 'por_receta', ''),
(53, 'Calabaza', 'gramos', 3000.00, 'por_receta', ''),
(54, 'Camarón gigante para pelar', 'pieza', 3000.00, 'por_receta', ''),
(55, 'Cebolla', 'pieza', 3000.00, 'por_receta', ''),
(56, 'Chile en polvo', 'gramos', 3000.00, 'por_receta', ''),
(57, 'Coliflor', 'gramos', 3000.00, 'por_receta', ''),
(59, 'Dedos de surimi', 'pieza', 3000.00, 'unidad_completa', ''),
(60, 'Fideos', 'gramos', 3000.00, 'por_receta', ''),
(61, 'Fondo de res', 'mililitros', 3000.00, 'no_controlado', ''),
(62, 'Graby de naranja', 'mililitros', 3000.00, 'no_controlado', ''),
(63, 'Jugo de aguachile', 'mililitros', 3000.00, 'no_controlado', ''),
(64, 'Julianas de zanahoria', 'gramos', 3000.00, 'por_receta', ''),
(65, 'Limón', 'gramos', 3000.00, 'por_receta', ''),
(66, 'Mezcla de quesos', 'gramos', 3000.00, 'uso_general', ''),
(67, 'Morrón', 'gramos', 3000.00, 'por_receta', ''),
(69, 'Pasta chukasoba', 'gramos', 3000.00, 'por_receta', ''),
(70, 'Pasta frita', 'gramos', 3000.00, 'por_receta', ''),
(71, 'Queso crema', 'gramos', 3000.00, 'uso_general', ''),
(72, 'Refresco embotellado', 'pieza', 3000.00, 'unidad_completa', ''),
(73, 'Res', 'gramos', 3000.00, 'uso_general', ''),
(74, 'Rodajas de naranja', 'gramos', 3000.00, 'por_receta', ''),
(75, 'Salmón', 'gramos', 3000.00, 'por_receta', ''),
(76, 'Salsa de anguila', 'mililitros', 3000.00, 'no_controlado', ''),
(77, 'Salsa teriyaki (dulce)', 'mililitros', 3000.00, 'no_controlado', ''),
(78, 'Salsas orientales', 'mililitros', 3000.00, 'no_controlado', ''),
(79, 'Shisimi', 'gramos', 3000.00, 'uso_general', ''),
(80, 'Siracha', 'mililitros', 3000.00, 'no_controlado', ''),
(81, 'Tampico', 'gramos', 3000.00, 'uso_general', ''),
(82, 'Tortilla de harina', 'pieza', 3000.00, 'unidad_completa', ''),
(83, 'Tostada', 'pieza', 3000.00, 'unidad_completa', ''),
(85, 'Yakimeshi mini', 'gramos', 3000.00, 'por_receta', ''),
(86, 'Sal con Ajo', 'pizca', 3000.00, 'por_receta', ''),
(87, 'Aderezo Chipotle', 'gramos', 3000.00, 'por_receta', ''),
(88, 'Mezcla Horneado', 'gramos', 3000.00, 'por_receta', ''),
(89, 'Aderezo', 'gramos', 3000.00, 'uso_general', ''),
(90, 'Camaron Empanizado', 'gramos', 3000.00, 'por_receta', ''),
(91, 'Pollo Empanizado', 'gramos', 3000.00, 'por_receta', ''),
(92, 'Cebollin', 'gramos', 3000.00, 'por_receta', ''),
(93, 'Aderezo Cebolla Dulce', 'oz', 3000.00, 'uso_general', ''),
(94, 'Camaron Enchiloso', 'gramos', 3000.00, 'por_receta', '');

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
(582, 1, 'corte_caja', 'Creación de corte', '2025-08-19 10:10:17', 51),
(583, 1, 'ventas', 'Alta de venta', '2025-08-19 18:18:38', 102),
(584, NULL, 'cocina', 'Producto iniciado', '2025-08-19 18:19:16', 189),
(585, NULL, 'cocina', 'Producto iniciado', '2025-08-19 18:19:17', 190),
(586, NULL, 'cocina', 'Producto iniciado', '2025-08-19 18:19:18', 191),
(587, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 18:20:13', 189),
(588, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 18:20:14', 190),
(589, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 18:20:15', 191),
(590, 7, 'ventas', 'Alta de venta', '2025-08-19 18:43:24', 103),
(591, NULL, 'cocina', 'Producto iniciado', '2025-08-19 18:43:51', 192),
(592, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 18:43:55', 192),
(593, 6, 'ventas', 'Alta de venta', '2025-08-19 19:03:37', 104),
(594, NULL, 'cocina', 'Producto iniciado', '2025-08-19 19:03:48', 193),
(595, NULL, 'cocina', 'Producto iniciado', '2025-08-19 19:03:49', 194),
(596, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 19:03:51', 193),
(597, NULL, 'cocina', 'Producto marcado como listo', '2025-08-19 19:03:52', 194),
(598, 1, 'corte_caja', 'Cierre de corte', '2025-08-20 07:23:18', 51),
(599, 1, 'corte_caja', 'Creación de corte', '2025-08-20 07:31:53', 52),
(600, 6, 'ventas', 'Alta de venta', '2025-08-20 11:57:54', 105),
(601, 2, 'ventas', 'Alta de venta', '2025-08-20 14:43:07', 106),
(602, 6, 'ventas', 'Alta de venta', '2025-08-20 16:38:56', 107),
(603, NULL, 'cocina', 'Producto iniciado', '2025-08-20 16:39:04', 195),
(604, NULL, 'cocina', 'Producto iniciado', '2025-08-20 16:39:06', 196),
(605, NULL, 'cocina', 'Producto iniciado', '2025-08-20 16:39:07', 197),
(606, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 16:39:08', 195),
(607, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 16:39:09', 196),
(608, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 16:39:10', 197),
(609, 1, 'corte_caja', 'Cierre de corte', '2025-08-20 16:47:26', 52),
(610, 1, 'corte_caja', 'Creación de corte', '2025-08-20 17:21:53', 53),
(611, 6, 'ventas', 'Alta de venta', '2025-08-20 17:26:27', 108),
(612, NULL, 'cocina', 'Producto iniciado', '2025-08-20 17:46:53', 198),
(613, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 17:46:54', 198),
(614, 6, 'ventas', 'Alta de venta', '2025-08-20 17:57:33', 109),
(615, NULL, 'cocina', 'Producto iniciado', '2025-08-20 17:57:36', 199),
(616, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 17:57:38', 199),
(617, NULL, 'cocina', 'Producto iniciado', '2025-08-20 19:48:56', 200),
(618, NULL, 'cocina', 'Producto iniciado', '2025-08-20 19:48:57', 201),
(619, NULL, 'cocina', 'Producto iniciado', '2025-08-20 19:48:57', 202),
(620, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 19:48:58', 200),
(621, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 19:48:58', 201),
(622, NULL, 'cocina', 'Producto marcado como listo', '2025-08-20 19:48:59', 202),
(623, NULL, 'cocina', 'Producto iniciado', '2025-08-21 13:31:40', 203),
(624, NULL, 'cocina', 'Producto iniciado', '2025-08-21 13:31:41', 204),
(625, NULL, 'cocina', 'Producto marcado como listo', '2025-08-21 13:31:41', 203),
(626, NULL, 'cocina', 'Producto marcado como listo', '2025-08-21 13:31:43', 204),
(627, 1, 'corte_caja', 'Cierre de corte', '2025-08-21 14:17:16', 53);

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

--
-- Volcado de datos para la tabla `log_mesas`
--

INSERT INTO `log_mesas` (`id`, `mesa_id`, `venta_id`, `usuario_id`, `fecha_inicio`, `fecha_fin`) VALUES
(27, 1, 102, 6, '2025-08-19 17:35:10', '2025-08-19 19:07:12');

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
(3, 'Mesa 3', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 7, 1, 0, 4),
(4, 'Mesa 4', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(5, 'Mesa 5', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL),
(6, 'Mesa 6', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(7, 'Mesa 7', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(8, 'Mesa 8', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL),
(9, 'Mesa 9', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(10, 'Mesa 10', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(11, 'Mesa 11', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL),
(12, 'Mesa 12', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(13, 'Mesa 13', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(14, 'Mesa 14', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL),
(15, 'Mesa 15', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(16, 'Mesa 16', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(17, 'Mesa 17', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL),
(18, 'Mesa 18', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, 3),
(19, 'Mesa 19', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, 1, 1, 0, NULL),
(20, 'Mesa 20', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, 1, 2, 0, NULL);

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
(5, 51, 1, 'deposito', 500.00, 'fued', '2025-08-20 03:10:17');

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

--
-- Volcado de datos para la tabla `movimientos_insumos`
--

INSERT INTO `movimientos_insumos` (`id`, `tipo`, `usuario_id`, `usuario_destino_id`, `insumo_id`, `cantidad`, `observacion`, `fecha`, `qr_token`) VALUES
(1, 'salida', 1, NULL, 2, 200.00, NULL, '2025-07-17 12:56:29', 'cd126fc49564255127b084ee7f2b7007'),
(2, 'salida', 1, NULL, 2, 200.00, NULL, '2025-07-17 13:15:03', 'e64d53f36a158deef2f92a7ba80f9e69'),
(3, 'salida', 1, NULL, 2, 200.00, NULL, '2025-07-17 13:15:28', '7cf259a51488a184416802d46684598d'),
(4, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(5, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(6, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(7, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(8, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(9, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-17 13:25:21', '9050a553b4e93238c559692decb58977'),
(10, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(11, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(12, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(13, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(14, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(15, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-17 13:42:28', '3e291a045cc33e8e3fb6199f0e414ef9'),
(16, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(17, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(18, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(19, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(20, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(21, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-17 14:18:53', 'c0d19e29ac82c06e5425a67f11796754'),
(22, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(23, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(24, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(25, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(26, 'salida', 1, NULL, 5, 11.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(27, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-17 15:02:12', '6b63183e412bbd52222dc3af0f8ccb36'),
(28, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(29, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(30, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(31, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(32, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(33, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-17 17:04:20', '50b79fdc304c8c12420468a33635d572'),
(34, 'entrada', 2, 1, 1, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(35, 'entrada', 2, 1, 2, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(36, 'entrada', 2, 1, 3, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(37, 'entrada', 2, 1, 4, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(38, 'entrada', 2, 1, 5, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(39, 'entrada', 2, 1, 6, 1.00, 'se entrega dos cocas', '2025-07-17 17:45:15', '50b79fdc304c8c12420468a33635d572'),
(40, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(41, 'salida', 1, NULL, 2, 2.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(42, 'salida', 1, NULL, 3, 3.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(43, 'salida', 1, NULL, 4, 5.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(44, 'salida', 1, NULL, 5, 6.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(45, 'salida', 1, NULL, 6, 7.00, NULL, '2025-07-18 13:41:08', 'c3a4f3d3d2b0474c621b8bb9d479850a'),
(46, 'salida', 1, NULL, 1, 11.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(47, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(48, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(49, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(50, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(51, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-18 19:57:47', '2c87a6f872cff3cba803385517b4573b'),
(52, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(53, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(54, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(55, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(56, 'salida', 1, NULL, 5, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(57, 'salida', 1, NULL, 6, 1.00, NULL, '2025-07-18 20:01:40', '247a958a60b593780469df6c0fc163f0'),
(58, 'salida', 1, NULL, 1, 1000.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(59, 'salida', 1, NULL, 2, 30.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(60, 'salida', 1, NULL, 3, 500.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(61, 'salida', 1, NULL, 4, 10.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(62, 'salida', 1, NULL, 5, 1000.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(63, 'salida', 1, NULL, 6, 10.00, NULL, '2025-07-18 20:30:59', '8fdb10cf27952caf7c6b64c7d423b94d'),
(64, 'entrada', 2, 1, 1, 1000.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(65, 'entrada', 2, 1, 2, 30.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(66, 'entrada', 2, 1, 3, 500.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(67, 'entrada', 2, 1, 4, 10.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(68, 'entrada', 2, 1, 5, 1000.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(69, 'entrada', 2, 1, 6, 10.00, 'Checar arroz', '2025-07-18 20:32:23', '8fdb10cf27952caf7c6b64c7d423b94d'),
(70, 'salida', 1, NULL, 1, 2000.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(71, 'salida', 1, NULL, 2, 40.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(72, 'salida', 1, NULL, 3, 1000.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(73, 'salida', 1, NULL, 4, 10.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(74, 'salida', 1, NULL, 5, 1000.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(75, 'salida', 1, NULL, 6, 10.00, NULL, '2025-07-18 21:27:53', '18362aae1efb507ecc36dda10b8975a0'),
(76, 'entrada', 2, 1, 1, 2000.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(77, 'entrada', 2, 1, 2, 40.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(78, 'entrada', 2, 1, 3, 1000.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(79, 'entrada', 2, 1, 4, 10.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(80, 'entrada', 2, 1, 5, 1000.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(81, 'entrada', 2, 1, 6, 10.00, 'Falto bolsa', '2025-07-18 21:28:30', '18362aae1efb507ecc36dda10b8975a0'),
(82, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-22 11:58:16', 'b1083d466e910ea22ade5641b7d2bef1'),
(83, 'salida', 1, NULL, 1, 1.00, NULL, '2025-07-22 12:00:07', 'd2edc6f65b964cb36eb448a527f4eef5'),
(84, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-22 12:00:07', 'd2edc6f65b964cb36eb448a527f4eef5'),
(85, 'salida', 1, NULL, 3, 1.00, NULL, '2025-07-22 12:00:07', 'd2edc6f65b964cb36eb448a527f4eef5'),
(86, 'salida', 1, NULL, 4, 1.00, NULL, '2025-07-22 12:00:07', 'd2edc6f65b964cb36eb448a527f4eef5'),
(87, 'salida', 1, NULL, 1, 2.00, NULL, '2025-07-22 12:03:54', 'fa6a5ec44ee85f28d0942835473f6046'),
(88, 'salida', 1, NULL, 1, 10.00, NULL, '2025-07-28 11:01:01', '40dcdc6c6f7715b8342cc8d4466c8c0e'),
(89, 'salida', 1, NULL, 2, 10.00, NULL, '2025-07-28 11:01:01', '40dcdc6c6f7715b8342cc8d4466c8c0e'),
(90, 'salida', 1, NULL, 3, 10.00, NULL, '2025-07-28 11:01:01', '40dcdc6c6f7715b8342cc8d4466c8c0e'),
(91, 'salida', 1, NULL, 1, 10.00, NULL, '2025-07-28 11:02:16', 'd65da1d5ef60dfed0e0ed7ec7631a331'),
(92, 'salida', 1, NULL, 2, 10.00, NULL, '2025-07-28 11:02:16', 'd65da1d5ef60dfed0e0ed7ec7631a331'),
(93, 'salida', 1, NULL, 3, 10.00, NULL, '2025-07-28 11:02:16', 'd65da1d5ef60dfed0e0ed7ec7631a331'),
(94, 'salida', 1, NULL, 4, 10.00, NULL, '2025-07-28 11:02:16', 'd65da1d5ef60dfed0e0ed7ec7631a331'),
(95, 'salida', 1, NULL, 1, 2.00, NULL, '2025-07-28 18:23:59', '90ad99b012166ec571ecf00f5b4ddab5'),
(96, 'salida', 1, NULL, 2, 2.00, NULL, '2025-07-28 18:23:59', '90ad99b012166ec571ecf00f5b4ddab5'),
(97, 'salida', 1, NULL, 3, 2.00, NULL, '2025-07-28 18:23:59', '90ad99b012166ec571ecf00f5b4ddab5'),
(98, 'salida', 1, NULL, 1, 10.00, NULL, '2025-07-28 18:25:05', 'f9be785fdd8819bfd5f227a2eb934d73'),
(99, 'salida', 1, NULL, 1, 2.00, NULL, '2025-07-28 18:32:51', 'ca98704d7d751372ba5597f016967815'),
(100, 'salida', 1, NULL, 1, 2.00, NULL, '2025-07-28 18:42:30', '33e6de62bdb40bf87c1cc5e1e87db5c1'),
(101, 'salida', 1, NULL, 2, 1.00, NULL, '2025-07-28 18:42:30', '33e6de62bdb40bf87c1cc5e1e87db5c1'),
(102, 'salida', 1, NULL, 3, 2.00, NULL, '2025-07-28 18:42:30', '33e6de62bdb40bf87c1cc5e1e87db5c1'),
(103, 'salida', 1, NULL, 1, 200.00, NULL, '2025-07-28 20:19:24', '49af5e083259d6fbecb8f4163603a2d8'),
(104, 'salida', 1, NULL, 2, 30.00, NULL, '2025-07-28 20:19:24', '49af5e083259d6fbecb8f4163603a2d8'),
(105, 'salida', 1, NULL, 3, 3.00, NULL, '2025-07-31 23:51:09', 'd975c18043e164015073379f567d05da');

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
(4, 'Refresco 600ml', 20.00, 'Refresco embotellado', 2992, 1, NULL, 1),
(5, 'Rollo California', 120.00, 'Salmón, arroz, alga nori', 10, 1, NULL, 3),
(6, 'Guamuchilito', 109.00, 'surimi, camarón empanizado, salsa de anguila', 37, 1, NULL, 8),
(7, 'Guerra', 125.00, 'camarón, ajonjolí, aguacate, salsa de anguila', 0, 1, NULL, 8),
(8, 'Tritón', 125.00, 'philadelphia, pepino, aguacate, surimi, atún ahumado, anguila, siracha', 100, 1, NULL, 8),
(9, 'Mechas', 139.00, 'philadelphia, pepino, aguacate, camarón, ajonjolí, kanikama, camarón empanizado,limón, sirracha, anguila, shisimi', 0, 1, NULL, 8),
(10, 'Supremo', 135.00, 'surimi, philadelphia, ajonjolí, tampico,  pollo capeado, salsa de anguila', 37, 1, NULL, 8),
(11, 'Roka crunch', 119.00, 'philadelphia, pepino, aguacate, camarón, surimi empanizado, zanahoria rallada, salsa de anguila', 60, 1, NULL, 8),
(12, 'Mar y tierra', 105.00, 'carne, camarón', 75, 1, NULL, 9),
(13, 'Cielo, mar y tierra', 109.00, 'pollo, carne, camarón', 150, 1, NULL, 9),
(14, '3 quesos', 115.00, 'camarón, carne, queso crema, queso americano, queso chihuahua', 75, 1, NULL, 9),
(15, 'Chiquilin roll', 115.00, 'philadelphia, pepino, aguacate, camarón empanizado, salsa de anguila, ajonjolí', 37, 1, NULL, 9),
(16, 'Maki roll', 105.00, '-----------------------------------', 42, 1, NULL, 9),
(17, 'Beef cheese', 119.00, 'carne, tocino, philadelphia, queso gratinado', 60, 1, NULL, 9),
(18, 'Cordon blue', 115.00, 'carne, tocino, philadelphia, queso gratinado', 100, 1, NULL, 9),
(19, 'Culichi roll', 125.00, 'carne, tampico, camarón', 37, 1, NULL, 9),
(20, 'Bacon cheese', 125.00, 'pollo, queso, tocino', 60, 1, NULL, 9),
(21, 'Cruch chiken', 125.00, 'pollo empanizado, tocino, chile serrano, salsa bbq, salsa de anguila', 0, 1, NULL, 9),
(22, 'Kito', 119.00, 'carne, tocino, queso, tampico', 37, 1, NULL, 9),
(23, 'Norteño', 115.00, 'camarón, tampico, queso, tocino, chile serrano', 0, 1, NULL, 9),
(24, 'Goloso roll', 135.00, 'res, pollo, tocino, queso o tampico', 37, 1, NULL, 9),
(25, 'Demon roll', 135.00, 'res, tocino, toping demon (camarón enchiloso)', 100, 1, NULL, 9),
(26, 'Nano max', 245.00, 'dedos de queso, dedos de surimi, carne, pollo, tocino, tampico, empanizado', 0, 1, NULL, 12),
(27, 'Nano XL', 325.00, 'dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 1.5 kg', 0, 1, NULL, 12),
(28, 'Nano t-plus', 375.00, 'dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 2 kg', 0, 1, NULL, 12),
(29, 'Chile volcán', 85.00, 'chile, 1 ingrediente a elegir, arroz, queso chihuahua,philadelphia', 0, 1, NULL, 10),
(30, 'Kushiagues', 75.00, 'camarón, pollo ó surimi', 0, 1, NULL, 10),
(31, 'Dedos de queso', 69.00, 'queso, empanizado (5 piezas)', 100, 1, NULL, 10),
(32, 'Tostada culichi', 75.00, 'tostada, camarón, pulpo, callo, pepino, cebolla morada, masago, chile serrano, chile en polvo, jugo de aguachile', 0, 1, NULL, 10),
(33, 'Tostada tropical', 75.00, 'tostada, atún, mango, camarón, callo, cebolla morada, chile en polvo, jugo de aguachile', 0, 1, NULL, 10),
(34, 'Empanada horneada', 115.00, 'tortilla de harina, carne, pollo, camarón,  mezcla de quesos, tampico, anguila y sirracha', 0, 1, NULL, 10),
(35, 'Rollitos', 75.00, 'philadelphia, queso chihuahua, ingrediente a elegir', 0, 1, NULL, 10),
(36, 'Gyozas', 95.00, 'philadelphia y camarón ó pollo y verduras (6 piezas)', 0, 1, NULL, 10),
(37, 'Papas a la francesa', 65.00, 'papas a la francesa y cátsup ó aderezo especial', 0, 1, NULL, 10),
(38, 'Papas gajo', 75.00, 'papas gajo y cátsup ó aderezo especial', 0, 1, NULL, 10),
(39, 'Ceviche tokyo', 165.00, 'cama de pepino, kanikama, camarón, aguacate, pulpo, jugo de aguachile', 0, 1, NULL, 3),
(40, 'Teriyaki krispy', 135.00, 'pollo empanizado, chile morrón, chile de arból, zanahoria, cebolla morada, cacahuate con salsa (salado)', 0, 1, NULL, 3),
(41, 'Teriyaki', 139.00, 'ingrediente a elegir, salteado de cebolla, zanahoria, calabaza, brócoli y coliflor, salsa teriyaki (dulce)', 0, 1, NULL, 3),
(42, 'Pollo mongol', 135.00, 'pollo capeado, cebolla, zanahoria, apio, chile serrano, chile morrón, chile de arból, salsas orientales, montado en arroz blanco', 0, 1, NULL, 3),
(43, 'Chow mein especial', 155.00, 'pasta frita, camarón, carne, pollo, vegetales, salsas orientales', 0, 1, NULL, 4),
(44, 'Chukasoba', 149.00, 'camarón, pulpo, vegetales, pasta chukasoba', 0, 1, NULL, 4),
(45, 'Fideo yurey', 165.00, 'fideo chino transparente, julianas de zanahoria y apio, cebolla, chile caribe y morrón y la proteína de tu elección', 0, 1, NULL, 4),
(46, 'Udon spicy', 179.00, 'julianas de zanahoria y cebolla, chile caribe, apio, chile de árbol, nuez de la india, ajonjolí, camarones capeados', 0, 1, NULL, 4),
(47, 'Orange chiken tokyo', 149.00, 'pollo capeado (300gr), graby de naranja, pepino, zanahoria, rodajas de naranja, ajonjolí', 0, 1, NULL, 3),
(48, 'Udon muchi', 125.00, 'pasta udon, vegetales, camarón y pollo', 0, 1, NULL, 4),
(49, 'Tokyo ramen', 125.00, 'pasta, vegetales, naruto, huevo, carne, camarón, fondo de res y cerdo', 0, 1, NULL, 4),
(50, 'Ramen gran meat', 125.00, 'pasta, vegetales, trozos de carne sazonada con salsas orientales', 100, 1, NULL, 4),
(51, 'Ramen yasai', 115.00, 'pasta, vegetales, fondo de res y cerdo', 0, 1, NULL, 4),
(52, 'Baby ramen', 119.00, 'pasta, vegetales, pollo a la plancha, salsas orientales, fondo de res y cerdo', 0, 1, NULL, 4),
(53, 'Cajun ramen', 155.00, 'fideos, vegetales, camarón gigante para pelar, fondo de res y cerdo, ajonjolí', 0, 1, NULL, 4),
(54, 'Gohan', 125.00, 'arroz blanco, res y pollo, base de philadelphia y tampico con rodajas de aguacate, camarones empanizados, ajonjolí', 0, 1, NULL, 5),
(55, 'gohan krispy', 115.00, 'arroz blanco, base de philadelphia, tampico y cubitos de aguacate, pollo y cebolla capeados, salsa de anguila, ajonjolí', 0, 1, NULL, 5),
(56, 'Yakimeshi', 115.00, 'arroz frito, vegetales, carne, pollo y tocino, philadelphia, tampico, aguacate, ajonjolí', 0, 1, NULL, 5),
(57, 'Yakimeshi roka', 125.00, 'arroz frito, pollo empanizado, philadelphia, aguacate y tampico', 0, 1, NULL, 5),
(58, 'Bomba', 115.00, 'bola de arroz, res, pollo, philadelphia, queso chihuahua, tampico , empanizada y cubierta de salsa de anguila', 0, 1, NULL, 5),
(59, 'Menú kids 1', 79.00, '1/2 rollo de pollo (6 piezas) y papas a la francesa', 100, 1, NULL, 3),
(60, 'Menú kids 2', 85.00, 'yakimeshi mini y papas a la francesa', 0, 1, NULL, 3),
(61, 'Menú kids 3', 79.00, 'dedos de queso (3 piezas) y papas a la francesa', 100, 1, NULL, 3),
(62, 'Chocoflan', 49.00, NULL, 0, 1, NULL, 2),
(63, 'Pay de Queso', 49.00, NULL, 0, 1, NULL, 2),
(64, 'Helado Tempura', 79.00, NULL, 0, 1, NULL, 2),
(65, 'Postre Especial', 79.00, NULL, 0, 1, NULL, 2),
(66, 'Té de Jazmín (Litro)', 33.00, NULL, 0, 1, NULL, 1),
(67, 'Té de Jazmín (Refil)', 35.00, NULL, 0, 1, NULL, 1),
(68, 'Limonada Natural', 35.00, NULL, 0, 1, NULL, 1),
(69, 'Limonada Mineral', 38.00, NULL, 0, 1, NULL, 1),
(70, 'Naranjada Natural', 35.00, NULL, 0, 1, NULL, 1),
(71, 'Naranjada Mineral', 38.00, NULL, 0, 1, NULL, 1),
(72, 'Agua de Tamarindo', 35.00, NULL, 0, 1, NULL, 1),
(73, 'Agua Mineral (355ml)', 35.00, NULL, 0, 1, NULL, 1),
(74, 'Calpico', 35.00, NULL, 0, 1, NULL, 1),
(75, 'Calpitamarindo', 39.00, NULL, 0, 1, NULL, 1),
(76, 'Refresco (335ml)', 29.00, NULL, 2995, 1, NULL, 1),
(77, 'Aderezo de Chipotle', 10.00, NULL, 0, 1, NULL, 6),
(78, 'Aderezo de Cilantro', 15.00, NULL, 0, 1, NULL, 6),
(79, 'Salsa Sriracha', 10.00, NULL, 0, 1, NULL, 6),
(80, 'Jugo de Aguachile', 15.00, NULL, 0, 1, NULL, 6),
(81, 'Ranch', 15.00, NULL, 0, 1, NULL, 6),
(82, 'Búfalo', 15.00, NULL, 0, 1, NULL, 6),
(83, 'BBQ', 15.00, NULL, 0, 1, NULL, 6),
(84, 'Soya Extra', 10.00, NULL, 0, 1, NULL, 6),
(85, 'Salsa de Anguila', 10.00, NULL, 0, 1, NULL, 6),
(86, 'Cebollitas o Chiles', 10.00, NULL, 0, 1, NULL, 6),
(87, 'Horneado Especial', 20.00, '(aderezo de chipotle, anguila y sriracha)', 0, 1, NULL, 7),
(88, 'Topping Kanikama', 35.00, '(ensalada de cangrejo)', 0, 1, NULL, 7),
(89, 'Topping Tampico', 15.00, '(ensalada de surimi)', 0, 1, NULL, 7),
(90, 'Topping Demon', 35.00, '(camarón, tocino, quesos, serrano y chichimi)', 0, 1, NULL, 7),
(91, 'Topping Chiquilín', 30.00, '(camarón empanizado, anguila y ajonjolí)', 0, 1, NULL, 7),
(92, 'Gladiador Roll', 139.00, 'Por dentro philadelphia, pepino y aguacate. Por fuera trozos de pulpo, queso spicy, shishimi y cebolla, bañado en salsa de anguila y ajonjolí. Rollo natural.', 37, 1, NULL, 9),
(93, 'Güerito Roll', 145.00, 'Por dentro camarón. Forrado con philadelphia y manchego, bañado en aderezo de chipotle, coronado con tocino, caribe y bañado en salsa sriracha. Empanizado.', 0, 1, NULL, 9),
(94, 'Eby Especial Roll', 145.00, 'Por dentro base, forrado con tampico cheese, bañado en aderezo de chipotle y coronado con camarón mariposa, aguacate, anguila y ajonjolí. Empanizado.', 37, 1, NULL, 9),
(95, 'Pakun Roll', 135.00, 'Relleno de tocino, por fuera topping de pollo y queso spicy, zanahoria. Acompañado de salsa anguila. Rollo natural.', 37, 1, NULL, 9),
(96, 'Rorris Roll', 135.00, 'Camarón y caribe por dentro, topping de tampico cheese, aguacate y bañados en salsa de anguila y ajonjolí. Empanizado.', 37, 1, NULL, 9),
(97, 'Royal Roll', 139.00, 'Carne y tocino por dentro, con topping de pollo. Empanizado, bañado con aderezo de chipotle, salsa de anguila y ajonjolí.', 0, 1, NULL, 9),
(98, 'Larry Roll', 155.00, 'Rollo relleno de camarón, forrado con salmón. Topping de surimi finamente picado, spicy, coronado con atún fresco y bañado en salsa de anguila y ajonjolí.', 0, 1, NULL, 11),
(99, 'Aguachile Especial Roll', 155.00, 'Rollo relleno de philadelphia, pepino y aguacate. Forrado de chile serrano finamente picado, coronado con un aguachile especial de camarón, pulpo, callo y aguacate.', 0, 1, NULL, 11),
(100, 'Mordik Roll', 145.00, 'Rollo relleno de tocino, montado doble con queso gratinado, mezcla de quesos spicy, coronado con camarones empanizados y bañado en salsa de anguila y ajonjolí.', 37, 1, NULL, 11),
(101, 'Maney Roll', 165.00, 'Relleno de philadelphia, pepino y aguacate. Forrado de aguacate fresco y topping con camarón, medallón de atún, callo, mango y cebolla morada. Acompañado de salsa aguachile. Rollo natural.', 0, 1, NULL, 11),
(102, 'Onigiri', 59.00, '1 pieza de triángulo de arroz blanco, con un toque ligero de philadelphia, forrado de alga, cubierto de ajonjolí y relleno opcional de pollo con verduras (col morada y zanahoria) o atún con aderezo especial de mayonesa y cebollín.', 0, 1, NULL, 3),
(103, 'Dumplings', 95.00, 'Orden de 6 piezas de dumplings, rellenos de carne molida de cerdo. Sazonados orientalmente y acompañado con salsa macha.', 0, 1, NULL, 3),
(104, 'Boneless', 135.00, '250gr. de boneless con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel o mermelada de chipotle).', 0, 1, NULL, 3),
(105, 'Alitas', 135.00, '250gr. de alitas con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel ó mermelada de chipotle).', 0, 1, NULL, 3),
(106, 'Sopa pho', 149.00, 'Rico fondo de pollo con vegetales, pechuga de pollo, fideos chinos y chile de árbol. Coronado con 4 piezas de dumplings.', 0, 1, NULL, 3),
(107, 'Yummy roll', 159.00, 'Alga por fuera, relleno de camarón, philadelphia, pepino y aguacate. Gratinado con queso spicy de la casa. Coronado con camarón, aguacate y bañado en salsa de anguila y ajonjolí.', 37, 1, NULL, 3),
(108, 'Cebolla Caramelizada', 10.00, 'Cebolla Caramelizada', 100, 1, NULL, NULL),
(109, 'Kintaro', 102.00, 'Kintaro', 20, 1, NULL, NULL),
(110, 'Guamuchilito Especial', 123.00, 'Guamuchilito Especial', 37, 1, NULL, NULL),
(111, 'Juny', 333.00, 'Juny', 37, 1, NULL, NULL),
(112, 'Pork Spicy', 122.00, 'Pork Spicy', 37, 1, NULL, NULL),
(113, 'Rollo natural Guamuchilito', 122.00, 'Rollo Natural', 37, 1, NULL, NULL),
(114, 'Rollo Natural Guamuchilito Especial', 102.00, 'Rollo Especial', 37, 1, NULL, NULL),
(115, 'Rollo Natural Guerra', 121.00, 'Rollo Natural', 10, 1, NULL, NULL),
(116, 'Rollo Natural Triton', 102.00, 'Rollo Natural', 10, 1, NULL, NULL),
(117, 'Rollo California Natural', 122.00, 'Rollo Natural', 10, 1, NULL, NULL),
(118, 'Rollo Natural Mecha', 1122.00, 'Rollo Natural', 10, 1, NULL, NULL),
(119, 'Rollo Supremo Natural', 103.00, 'Rollo Natural', 37, 1, NULL, NULL);

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

--
-- Volcado de datos para la tabla `qrs_insumo`
--

INSERT INTO `qrs_insumo` (`id`, `token`, `json_data`, `estado`, `creado_por`, `creado_en`, `expiracion`, `pdf_envio`, `pdf_recepcion`) VALUES
(11, '2c87a6f872cff3cba803385517b4573b', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":11},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":1},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":5,\"nombre\":\"Salsa Soya\",\"unidad\":\"ml\",\"cantidad\":1},{\"id\":6,\"nombre\":\"refrescos fanta\",\"unidad\":\"por_receta\",\"cantidad\":1}]', 'pendiente', 1, '2025-07-18 19:57:47', NULL, 'archivos/bodega/pdfs/qr_2c87a6f872cff3cba803385517b4573b.pdf', NULL),
(12, '247a958a60b593780469df6c0fc163f0', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":1},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":1},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":5,\"nombre\":\"Salsa Soya\",\"unidad\":\"ml\",\"cantidad\":1},{\"id\":6,\"nombre\":\"refrescos fanta\",\"unidad\":\"por_receta\",\"cantidad\":1}]', 'pendiente', 1, '2025-07-18 20:01:40', NULL, 'archivos/bodega/pdfs/qr_247a958a60b593780469df6c0fc163f0.pdf', NULL),
(13, '8fdb10cf27952caf7c6b64c7d423b94d', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":1000},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":30},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":500},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":10},{\"id\":5,\"nombre\":\"Salsa Soya\",\"unidad\":\"ml\",\"cantidad\":1000},{\"id\":6,\"nombre\":\"refrescos fanta\",\"unidad\":\"por_receta\",\"cantidad\":10}]', 'confirmado', 1, '2025-07-18 20:30:59', NULL, 'archivos/bodega/pdfs/qr_8fdb10cf27952caf7c6b64c7d423b94d.pdf', 'uploads/qrs/recepcion_8fdb10cf27952caf7c6b64c7d423b94d.pdf'),
(14, '18362aae1efb507ecc36dda10b8975a0', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":2000},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":40},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":1000},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":10},{\"id\":5,\"nombre\":\"Salsa Soya\",\"unidad\":\"ml\",\"cantidad\":1000},{\"id\":6,\"nombre\":\"refrescos fanta\",\"unidad\":\"por_receta\",\"cantidad\":10}]', 'confirmado', 1, '2025-07-18 21:27:53', NULL, 'archivos/bodega/pdfs/qr_18362aae1efb507ecc36dda10b8975a0.pdf', 'uploads/qrs/recepcion_18362aae1efb507ecc36dda10b8975a0.pdf'),
(15, 'b1083d466e910ea22ade5641b7d2bef1', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":1}]', 'pendiente', 1, '2025-07-22 11:58:16', NULL, 'archivos/bodega/pdfs/qr_b1083d466e910ea22ade5641b7d2bef1.pdf', NULL),
(16, 'd2edc6f65b964cb36eb448a527f4eef5', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":1},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":1},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":1}]', 'pendiente', 1, '2025-07-22 12:00:07', NULL, 'archivos/bodega/pdfs/qr_d2edc6f65b964cb36eb448a527f4eef5.pdf', NULL),
(17, 'fa6a5ec44ee85f28d0942835473f6046', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":2}]', 'pendiente', 1, '2025-07-22 12:03:54', NULL, 'archivos/bodega/pdfs/qr_fa6a5ec44ee85f28d0942835473f6046.pdf', NULL),
(18, '40dcdc6c6f7715b8342cc8d4466c8c0e', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":10},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":10},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":10}]', 'pendiente', 1, '2025-07-28 11:01:01', NULL, 'archivos/bodega/pdfs/qr_40dcdc6c6f7715b8342cc8d4466c8c0e.pdf', NULL),
(19, 'd65da1d5ef60dfed0e0ed7ec7631a331', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":10},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":10},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":10},{\"id\":4,\"nombre\":\"Refresco en lata\",\"unidad\":\"piezas\",\"cantidad\":10}]', 'pendiente', 1, '2025-07-28 11:02:16', NULL, 'archivos/bodega/pdfs/qr_d65da1d5ef60dfed0e0ed7ec7631a331.pdf', NULL),
(20, '90ad99b012166ec571ecf00f5b4ddab5', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":2},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":2},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":2}]', 'pendiente', 1, '2025-07-28 18:23:59', NULL, 'archivos/bodega/pdfs/qr_90ad99b012166ec571ecf00f5b4ddab5.pdf', NULL),
(21, 'f9be785fdd8819bfd5f227a2eb934d73', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":10}]', 'pendiente', 1, '2025-07-28 18:25:05', NULL, 'archivos/bodega/pdfs/qr_f9be785fdd8819bfd5f227a2eb934d73.pdf', NULL),
(22, 'ca98704d7d751372ba5597f016967815', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":2}]', 'pendiente', 1, '2025-07-28 18:32:51', NULL, 'archivos/bodega/pdfs/qr_ca98704d7d751372ba5597f016967815.pdf', NULL),
(23, '33e6de62bdb40bf87c1cc5e1e87db5c1', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":2},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":1},{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":2}]', 'pendiente', 1, '2025-07-28 18:42:30', NULL, 'archivos/bodega/pdfs/qr_33e6de62bdb40bf87c1cc5e1e87db5c1.pdf', NULL),
(24, '49af5e083259d6fbecb8f4163603a2d8', '[{\"id\":1,\"nombre\":\"Arroz para sushi\",\"unidad\":\"gramos\",\"cantidad\":200},{\"id\":2,\"nombre\":\"Alga Nori\",\"unidad\":\"piezas\",\"cantidad\":30}]', 'pendiente', 1, '2025-07-28 20:19:24', NULL, 'archivos/bodega/pdfs/qr_49af5e083259d6fbecb8f4163603a2d8.pdf', NULL),
(25, 'd975c18043e164015073379f567d05da', '[{\"id\":3,\"nombre\":\"Salmón fresco\",\"unidad\":\"gramos\",\"cantidad\":3}]', 'pendiente', 1, '2025-07-31 23:51:09', NULL, 'archivos/bodega/pdfs/qr_d975c18043e164015073379f567d05da.pdf', NULL);

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
(1, 5, 1, 300.00),
(2, 5, 2, 2.00),
(3, 5, 3, 100.00),
(4, 4, 4, 1.00),
(5, 4, 72, 0.00),
(6, 5, 75, 0.00),
(7, 5, 2, 0.00),
(8, 6, 7, 50.00),
(9, 6, 76, 0.00),
(10, 7, 10, 0.00),
(11, 7, 16, 0.00),
(12, 7, 24, 0.00),
(13, 7, 76, 0.00),
(14, 8, 12, 0.00),
(15, 8, 36, 0.00),
(16, 8, 24, 0.00),
(17, 8, 7, 0.00),
(18, 8, 51, 0.00),
(19, 8, 19, 0.00),
(20, 8, 80, 0.00),
(21, 9, 12, 0.00),
(22, 9, 36, 0.00),
(23, 9, 24, 0.00),
(24, 9, 10, 0.00),
(25, 9, 16, 0.00),
(26, 9, 23, 0.00),
(27, 9, 65, 0.00),
(28, 9, 19, 0.00),
(29, 9, 79, 0.00),
(30, 10, 7, 50.00),
(31, 10, 12, 0.00),
(32, 10, 16, 0.00),
(33, 10, 81, 0.00),
(34, 10, 76, 0.00),
(35, 11, 12, 0.00),
(36, 11, 36, 0.00),
(37, 11, 24, 0.00),
(38, 11, 10, 0.00),
(39, 11, 76, 0.00),
(40, 12, 10, 0.00),
(41, 13, 9, 20.00),
(42, 13, 10, 0.00),
(43, 14, 10, 0.00),
(44, 14, 71, 0.00),
(45, 14, 15, 0.00),
(46, 14, 11, 0.00),
(47, 15, 12, 0.00),
(48, 15, 36, 15.00),
(49, 15, 24, 0.00),
(50, 15, 76, 0.00),
(51, 15, 16, 0.00),
(52, 17, 8, 0.00),
(53, 17, 12, 0.00),
(54, 18, 8, 15.00),
(55, 18, 12, 0.00),
(56, 19, 81, 0.00),
(57, 19, 10, 0.00),
(58, 20, 9, 0.00),
(59, 20, 8, 30.00),
(60, 21, 8, 0.00),
(61, 21, 21, 0.00),
(62, 21, 20, 0.00),
(63, 21, 76, 0.00),
(64, 22, 8, 15.00),
(65, 22, 81, 0.00),
(66, 23, 10, 0.00),
(67, 23, 81, 0.00),
(68, 23, 8, 0.00),
(69, 23, 21, 0.00),
(70, 24, 73, 0.00),
(71, 24, 9, 20.00),
(72, 24, 8, 15.00),
(73, 24, 81, 0.00),
(74, 25, 73, 0.00),
(75, 25, 8, 15.00),
(76, 26, 25, 0.00),
(77, 26, 59, 0.00),
(78, 26, 9, 0.00),
(79, 26, 8, 0.00),
(80, 26, 81, 0.00),
(81, 27, 25, 0.00),
(82, 27, 8, 0.00),
(83, 27, 81, 0.00),
(84, 28, 25, 0.00),
(85, 28, 8, 0.00),
(86, 28, 81, 0.00),
(87, 29, 11, 0.00),
(88, 29, 12, 0.00),
(89, 30, 10, 0.00),
(90, 30, 9, 0.00),
(91, 30, 7, 0.00),
(92, 32, 83, 0.00),
(93, 32, 10, 0.00),
(94, 32, 33, 0.00),
(95, 32, 41, 0.00),
(96, 32, 36, 0.00),
(97, 32, 29, 0.00),
(98, 32, 37, 0.00),
(99, 32, 21, 0.00),
(100, 32, 56, 0.00),
(101, 32, 63, 0.00),
(102, 33, 83, 0.00),
(103, 33, 40, 0.00),
(104, 33, 26, 0.00),
(105, 33, 10, 0.00),
(106, 33, 41, 0.00),
(107, 33, 29, 0.00),
(108, 33, 56, 0.00),
(109, 33, 63, 0.00),
(110, 34, 82, 0.00),
(111, 34, 9, 0.00),
(112, 34, 10, 0.00),
(113, 34, 66, 0.00),
(114, 34, 81, 0.00),
(115, 34, 19, 0.00),
(116, 35, 12, 0.00),
(117, 35, 11, 0.00),
(118, 36, 12, 0.00),
(119, 36, 10, 0.00),
(120, 36, 9, 0.00),
(121, 37, 39, 0.00),
(122, 38, 39, 0.00),
(123, 39, 23, 0.00),
(124, 39, 10, 0.00),
(125, 39, 24, 0.00),
(126, 39, 33, 0.00),
(127, 39, 63, 0.00),
(128, 40, 22, 0.00),
(129, 40, 34, 0.00),
(130, 40, 29, 0.00),
(131, 40, 52, 0.00),
(132, 41, 34, 0.00),
(133, 41, 53, 0.00),
(134, 41, 44, 0.00),
(135, 41, 57, 0.00),
(136, 41, 77, 0.00),
(137, 42, 55, 0.00),
(138, 42, 34, 0.00),
(139, 42, 35, 0.00),
(140, 42, 21, 0.00),
(141, 42, 22, 0.00),
(142, 42, 78, 0.00),
(143, 43, 70, 0.00),
(144, 43, 10, 0.00),
(145, 43, 9, 0.00),
(146, 43, 78, 0.00),
(147, 44, 10, 0.00),
(148, 44, 33, 0.00),
(149, 44, 69, 0.00),
(150, 45, 43, 0.00),
(151, 45, 64, 0.00),
(152, 45, 35, 0.00),
(153, 45, 55, 0.00),
(154, 45, 32, 0.00),
(155, 45, 67, 0.00),
(156, 46, 64, 0.00),
(157, 46, 55, 0.00),
(158, 46, 32, 0.00),
(159, 46, 35, 0.00),
(160, 46, 45, 0.00),
(161, 46, 38, 0.00),
(162, 46, 16, 0.00),
(163, 47, 62, 0.00),
(164, 47, 36, 0.00),
(165, 47, 34, 0.00),
(166, 47, 74, 0.00),
(167, 47, 16, 0.00),
(168, 48, 46, 0.00),
(169, 48, 10, 0.00),
(170, 48, 9, 0.00),
(171, 49, 50, 0.00),
(172, 49, 47, 0.00),
(173, 49, 10, 0.00),
(174, 49, 61, 0.00),
(175, 49, 48, 0.00),
(177, 51, 61, 0.00),
(178, 51, 48, 0.00),
(179, 52, 78, 0.00),
(180, 52, 61, 0.00),
(181, 52, 48, 0.00),
(182, 53, 60, 0.00),
(183, 53, 54, 0.00),
(184, 53, 61, 0.00),
(185, 53, 48, 0.00),
(186, 53, 16, 0.00),
(187, 54, 13, 0.00),
(188, 54, 73, 0.00),
(189, 54, 9, 0.00),
(190, 54, 16, 0.00),
(191, 55, 13, 0.00),
(192, 55, 81, 0.00),
(193, 55, 9, 0.00),
(194, 55, 76, 0.00),
(195, 55, 16, 0.00),
(196, 56, 9, 0.00),
(197, 56, 8, 0.00),
(198, 56, 12, 0.00),
(199, 56, 81, 0.00),
(200, 56, 24, 0.00),
(201, 56, 16, 0.00),
(202, 57, 12, 0.00),
(203, 57, 24, 0.00),
(204, 57, 81, 0.00),
(205, 58, 73, 0.00),
(206, 58, 9, 0.00),
(207, 58, 12, 0.00),
(208, 58, 11, 0.00),
(209, 58, 81, 0.00),
(210, 60, 85, 0.00),
(348, 12, 90, 25.00),
(349, 12, 14, 40.00),
(350, 13, 90, 20.00),
(351, 13, 14, 20.00),
(352, 14, 90, 25.00),
(353, 14, 14, 40.00),
(354, 17, 14, 50.00),
(355, 18, 14, 30.00),
(356, 19, 14, 50.00),
(357, 20, 21, 5.00),
(358, 22, 14, 30.00),
(359, 23, 90, 25.00),
(360, 23, 18, 35.00),
(361, 24, 14, 20.00),
(362, 25, 14, 30.00),
(363, 110, 7, 50.00),
(364, 7, 90, 45.00),
(365, 116, 36, 15.00),
(366, 116, 7, 5.00),
(367, 5, 90, 45.00),
(368, 9, 90, 45.00),
(369, 92, 36, 15.00),
(370, 93, 90, 45.00),
(371, 95, 8, 35.00),
(372, 96, 90, 45.00),
(373, 96, 32, 5.00),
(374, 97, 14, 30.00),
(375, 97, 8, 15.00),
(376, 98, 90, 45.00),
(377, 98, 12, 10.00),
(378, 99, 36, 15.00),
(379, 99, 12, 10.00),
(380, 101, 24, 35.00),
(381, 101, 36, 15.00),
(382, 101, 12, 10.00),
(383, 101, 33, 30.00);

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
  `propina` decimal(10,2) DEFAULT 0.00,
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

INSERT INTO `tickets` (`id`, `venta_id`, `folio`, `total`, `propina`, `fecha`, `usuario_id`, `monto_recibido`, `tipo_pago`, `sede_id`, `mesa_nombre`, `mesero_nombre`, `fecha_inicio`, `fecha_fin`, `tiempo_servicio`, `nombre_negocio`, `direccion_negocio`, `rfc_negocio`, `telefono_negocio`, `tipo_entrega`, `tarjeta_marca_id`, `tarjeta_banco_id`, `boucher`, `cheque_numero`, `cheque_banco_id`) VALUES
(121, 102, 2001, 442.00, 56.00, '2025-08-20 03:07:12', NULL, 442.00, 'boucher', 1, 'Mesa 1', 'N/A', NULL, '2025-08-20 03:07:11', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'mesa', 2, 8, '552255', NULL, NULL),
(122, 103, 2002, 20.00, 0.00, '2025-08-20 03:08:01', NULL, 30.00, 'efectivo', 1, 'Venta rápida', 'pancho mesero', NULL, '2025-08-20 03:08:01', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(123, 104, 2003, 168.00, 88.00, '2025-08-20 03:10:00', NULL, 168.00, 'cheque', 1, 'Venta rápida', 'alejandro mesero', NULL, '2025-08-20 03:10:00', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, '756875986', 4),
(124, 107, 2004, 15.00, 0.00, '2025-08-21 00:40:57', NULL, 15.00, 'efectivo', 1, 'Venta rápida', 'alejandro mesero', NULL, '2025-08-21 00:40:57', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(125, 106, 2005, 10.00, 0.00, '2025-08-21 00:42:30', NULL, 10.00, 'efectivo', 1, 'Venta rápida', 'Carlos Mesero', NULL, '2025-08-21 00:42:30', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(126, 105, 2006, 465.00, 0.00, '2025-08-21 00:47:03', NULL, 465.00, 'efectivo', 1, 'Venta rápida', 'alejandro mesero', NULL, '2025-08-21 00:47:03', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(127, 108, 2007, 38.00, 0.00, '2025-08-21 21:32:06', NULL, 38.00, 'efectivo', 1, 'Venta rápida', 'alejandro mesero', NULL, '2025-08-21 21:32:06', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(128, 109, 2008, 173.00, 0.00, '2025-08-21 21:50:37', NULL, 180.00, 'efectivo', 1, 'Venta rápida', 'alejandro mesero', NULL, '2025-08-21 21:50:37', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '618 322 2352', 'rapido', NULL, NULL, NULL, NULL, NULL);

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
(175, 121, 77, 1, 10.00),
(176, 121, 86, 1, 10.00),
(177, 121, 113, 3, 122.00),
(178, 122, 77, 2, 10.00),
(179, 123, 77, 1, 10.00),
(180, 123, 72, 2, 35.00),
(181, 124, 82, 1, 15.00),
(182, 125, 77, 1, 10.00),
(183, 126, 53, 3, 155.00),
(184, 127, 71, 1, 38.00),
(185, 128, 66, 1, 33.00),
(186, 128, 4, 1, 20.00),
(187, 128, 4, 1, 20.00),
(188, 128, 4, 3, 20.00),
(189, 128, 4, 1, 20.00),
(190, 128, 4, 1, 20.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `rol` enum('cajero','mesero','admin','repartidor','cocinero') NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `contrasena`, `rol`, `activo`) VALUES
(1, 'Administrador', 'admin', 'admin', 'admin', 1),
(2, 'Carlos Mesero', 'carlos', 'd033e22ae348aeb5660fc2140aec35850c4da997', 'mesero', 1),
(3, 'Laura Cajera', 'laura', 'admin', 'cajero', 1),
(4, 'Juan reparto', 'juan', 'admin', 'repartidor', 1),
(5, 'Alan Omar Valles Canales', 'luisa', 'admin', 'cocinero', 1),
(6, 'alejandro mesero', 'alex', 'd033e22ae348aeb5660fc2140aec35850c4da997', 'mesero', 1),
(7, 'pancho mesero', 'pancho', 'admin', 'mesero', 1),
(8, 'Jose Angel Valdez Flores', 'AngelV', 'admin', 'cocinero', 1),
(9, 'Daniel Gutierrez Amador', 'DaniG', 'admin', 'cocinero', 1),
(10, 'Edson Darihec Reyes Villa', 'Darihec ', 'admin', 'cocinero', 1),
(11, 'Hector Osbaldo Hernandez Orona', 'HectorO', 'admin', 'cocinero', 1),
(12, 'Henry Adahyr Coronel Gamiz', 'Henry ', 'admin', 'cocinero', 1),
(13, 'Jose Arturo Montoya Campos', 'JoseA', 'admin', 'cocinero', 1),
(14, 'Kevin de Jesus Rosales Valles', 'KevinJ', 'admin', 'cocinero', 1),
(15, 'Roberto Garcia Soto', 'RobertoG', 'admin', 'cocinero', 1),
(16, 'Luis Varela Rueda', 'LuisV', 'admin', 'cocinero', 1);

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
(41, 5, 6);

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
  `sede_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `fecha`, `mesa_id`, `repartidor_id`, `tipo_entrega`, `usuario_id`, `total`, `estatus`, `entregado`, `estado_entrega`, `fecha_asignacion`, `fecha_inicio`, `fecha_entrega`, `seudonimo_entrega`, `foto_entrega`, `corte_id`, `cajero_id`, `observacion`, `sede_id`) VALUES
(102, '2025-08-19 18:18:38', 1, NULL, 'mesa', NULL, 386.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 51, 1, NULL, 1),
(103, '2025-08-19 18:43:24', NULL, NULL, 'rapido', 7, 20.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 51, 1, '', 1),
(104, '2025-08-19 19:03:37', NULL, NULL, 'rapido', 6, 80.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 51, 1, '', 1),
(105, '2025-08-20 11:57:54', NULL, NULL, 'rapido', 6, 465.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 52, 1, '', 1),
(106, '2025-08-20 14:43:07', NULL, NULL, 'rapido', 2, 10.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 52, 1, '', 1),
(107, '2025-08-20 16:38:56', NULL, NULL, 'rapido', 6, 15.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 52, 1, '', 1),
(108, '2025-08-20 17:26:27', NULL, NULL, 'rapido', 6, 38.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 53, 1, '', 1),
(109, '2025-08-20 17:57:33', NULL, NULL, 'rapido', 6, 173.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 53, 1, '', 1);

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
(189, 102, 77, 1, 10.00, 1, '2025-08-19 18:18:38', '2025-08-19 18:20:49', 'entregado', NULL),
(190, 102, 86, 1, 10.00, 1, '2025-08-19 18:18:54', '2025-08-19 18:20:50', 'entregado', NULL),
(191, 102, 113, 3, 122.00, 1, '2025-08-19 18:19:05', '2025-08-19 18:20:51', 'entregado', NULL),
(192, 103, 77, 2, 10.00, 1, '2025-08-19 18:43:24', '2025-08-19 18:44:18', 'entregado', NULL),
(193, 104, 77, 1, 10.00, 1, '2025-08-19 19:03:37', '2025-08-19 19:03:53', 'entregado', NULL),
(194, 104, 72, 2, 35.00, 1, '2025-08-19 19:03:37', '2025-08-19 19:03:54', 'entregado', NULL),
(195, 105, 53, 3, 155.00, 1, '2025-08-20 11:57:54', '2025-08-20 16:39:11', 'entregado', NULL),
(196, 106, 77, 1, 10.00, 1, '2025-08-20 14:43:07', '2025-08-20 16:39:12', 'entregado', NULL),
(197, 107, 82, 1, 15.00, 1, '2025-08-20 16:38:56', '2025-08-20 16:39:13', 'entregado', NULL),
(198, 108, 71, 1, 38.00, 1, '2025-08-20 17:26:27', '2025-08-20 17:46:54', 'entregado', NULL),
(199, 109, 66, 1, 33.00, 1, '2025-08-20 17:57:33', '2025-08-20 17:57:39', 'entregado', NULL),
(200, 109, 4, 1, 20.00, 1, '2025-08-20 19:31:14', '2025-08-20 19:49:00', 'entregado', NULL),
(201, 109, 4, 1, 20.00, 1, '2025-08-20 19:44:45', '2025-08-20 19:49:00', 'entregado', NULL),
(202, 109, 4, 3, 20.00, 1, '2025-08-20 19:47:51', '2025-08-20 19:49:01', 'entregado', NULL),
(203, 109, 4, 1, 20.00, 1, '2025-08-20 23:48:25', '2025-08-21 13:31:42', 'entregado', NULL),
(204, 109, 4, 1, 20.00, 1, '2025-08-21 07:08:39', '2025-08-21 13:31:44', 'entregado', NULL);

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
(130, 189, 'pendiente', 'en_preparacion', '2025-08-19 18:19:16'),
(131, 190, 'pendiente', 'en_preparacion', '2025-08-19 18:19:17'),
(132, 191, 'pendiente', 'en_preparacion', '2025-08-19 18:19:18'),
(133, 189, 'en_preparacion', 'listo', '2025-08-19 18:20:13'),
(134, 190, 'en_preparacion', 'listo', '2025-08-19 18:20:14'),
(135, 191, 'en_preparacion', 'listo', '2025-08-19 18:20:15'),
(136, 189, 'listo', 'entregado', '2025-08-19 18:20:49'),
(137, 190, 'listo', 'entregado', '2025-08-19 18:20:50'),
(138, 191, 'listo', 'entregado', '2025-08-19 18:20:51'),
(139, 192, 'pendiente', 'en_preparacion', '2025-08-19 18:43:51'),
(140, 192, 'en_preparacion', 'listo', '2025-08-19 18:43:55'),
(141, 192, 'listo', 'entregado', '2025-08-19 18:44:18'),
(142, 193, 'pendiente', 'en_preparacion', '2025-08-19 19:03:48'),
(143, 194, 'pendiente', 'en_preparacion', '2025-08-19 19:03:49'),
(144, 193, 'en_preparacion', 'listo', '2025-08-19 19:03:51'),
(145, 194, 'en_preparacion', 'listo', '2025-08-19 19:03:52'),
(146, 193, 'listo', 'entregado', '2025-08-19 19:03:53'),
(147, 194, 'listo', 'entregado', '2025-08-19 19:03:54'),
(148, 195, 'pendiente', 'en_preparacion', '2025-08-20 16:39:04'),
(149, 196, 'pendiente', 'en_preparacion', '2025-08-20 16:39:06'),
(150, 197, 'pendiente', 'en_preparacion', '2025-08-20 16:39:07'),
(151, 195, 'en_preparacion', 'listo', '2025-08-20 16:39:08'),
(152, 196, 'en_preparacion', 'listo', '2025-08-20 16:39:08'),
(153, 197, 'en_preparacion', 'listo', '2025-08-20 16:39:10'),
(154, 195, 'listo', 'entregado', '2025-08-20 16:39:11'),
(155, 196, 'listo', 'entregado', '2025-08-20 16:39:12'),
(156, 197, 'listo', 'entregado', '2025-08-20 16:39:13'),
(157, 198, 'pendiente', 'en_preparacion', '2025-08-20 17:46:53'),
(158, 198, 'en_preparacion', 'listo', '2025-08-20 17:46:54'),
(159, 198, 'listo', 'entregado', '2025-08-20 17:46:54'),
(160, 199, 'pendiente', 'en_preparacion', '2025-08-20 17:57:36'),
(161, 199, 'en_preparacion', 'listo', '2025-08-20 17:57:38'),
(162, 199, 'listo', 'entregado', '2025-08-20 17:57:39'),
(163, 200, 'pendiente', 'en_preparacion', '2025-08-20 19:48:56'),
(164, 201, 'pendiente', 'en_preparacion', '2025-08-20 19:48:56'),
(165, 202, 'pendiente', 'en_preparacion', '2025-08-20 19:48:57'),
(166, 200, 'en_preparacion', 'listo', '2025-08-20 19:48:58'),
(167, 201, 'en_preparacion', 'listo', '2025-08-20 19:48:58'),
(168, 202, 'en_preparacion', 'listo', '2025-08-20 19:48:59'),
(169, 200, 'listo', 'entregado', '2025-08-20 19:49:00'),
(170, 201, 'listo', 'entregado', '2025-08-20 19:49:00'),
(171, 202, 'listo', 'entregado', '2025-08-20 19:49:01'),
(172, 203, 'pendiente', 'en_preparacion', '2025-08-21 13:31:40'),
(173, 204, 'pendiente', 'en_preparacion', '2025-08-21 13:31:41'),
(174, 203, 'en_preparacion', 'listo', '2025-08-21 13:31:41'),
(175, 203, 'listo', 'entregado', '2025-08-21 13:31:42'),
(176, 204, 'en_preparacion', 'listo', '2025-08-21 13:31:43'),
(177, 204, 'listo', 'entregado', '2025-08-21 13:31:44');

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
,`total_propinas` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_resumen_pagos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_resumen_pagos` (
`corte_id` int(11)
,`tipo_pago` enum('efectivo','boucher','cheque')
,`total_productos` decimal(33,2)
,`total_propinas` decimal(32,2)
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
,`total_productos` decimal(33,2)
,`total_propinas` decimal(32,2)
,`total_general` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ventas_por_mesero`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ventas_por_mesero` (
`mesero_id` int(11)
,`mesero` varchar(100)
,`total_ventas` bigint(21)
,`total_productos` decimal(33,2)
,`total_propinas` decimal(32,2)
,`total_venta` decimal(32,2)
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
-- Estructura para la vista `vista_productos_mas_vendidos`
--
DROP TABLE IF EXISTS `vista_productos_mas_vendidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_productos_mas_vendidos`  AS SELECT `vd`.`producto_id` AS `producto_id`, `p`.`nombre` AS `nombre`, sum(`vd`.`cantidad`) AS `total_vendidos`, sum(`vd`.`cantidad` * `vd`.`precio_unitario`) AS `total_ingresos` FROM (`venta_detalles` `vd` join `productos` `p` on(`vd`.`producto_id` = `p`.`id`)) GROUP BY `vd`.`producto_id`, `p`.`nombre` ORDER BY sum(`vd`.`cantidad`) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_cortes`
--
DROP TABLE IF EXISTS `vista_resumen_cortes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_cortes`  AS SELECT `c`.`id` AS `corte_id`, `c`.`usuario_id` AS `usuario_id`, `c`.`fecha_inicio` AS `fecha_inicio`, `c`.`fecha_fin` AS `fecha_fin`, `c`.`fondo_inicial` AS `fondo_inicial`, `c`.`total` AS `total_corte`, coalesce(sum(`t`.`total`),0) AS `total_ventas`, coalesce(sum(`t`.`propina`),0) AS `total_propinas` FROM ((`corte_caja` `c` left join `ventas` `v` on(`v`.`corte_id` = `c`.`id` and `v`.`estatus` = 'cerrada')) left join `tickets` `t` on(`t`.`venta_id` = `v`.`id`)) GROUP BY `c`.`id` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_pagos`
--
DROP TABLE IF EXISTS `vista_resumen_pagos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_pagos`  AS SELECT `v`.`corte_id` AS `corte_id`, `t`.`tipo_pago` AS `tipo_pago`, sum(`t`.`total` - `t`.`propina`) AS `total_productos`, sum(`t`.`propina`) AS `total_propinas`, sum(`t`.`total`) AS `total_con_propina` FROM (`tickets` `t` join `ventas` `v` on(`t`.`venta_id` = `v`.`id`)) WHERE `v`.`estatus` = 'cerrada' GROUP BY `v`.`corte_id`, `t`.`tipo_pago` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas_diarias`
--
DROP TABLE IF EXISTS `vista_ventas_diarias`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ventas_diarias`  AS SELECT cast(`t`.`fecha` as date) AS `fecha`, count(0) AS `cantidad_ventas`, sum(`t`.`total` - `t`.`propina`) AS `total_productos`, sum(`t`.`propina`) AS `total_propinas`, sum(`t`.`total`) AS `total_general` FROM (`tickets` `t` join `ventas` `v` on(`t`.`venta_id` = `v`.`id`)) WHERE `v`.`estatus` = 'cerrada' GROUP BY cast(`t`.`fecha` as date) ORDER BY cast(`t`.`fecha` as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ventas_por_mesero`
--
DROP TABLE IF EXISTS `vista_ventas_por_mesero`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ventas_por_mesero`  AS SELECT `t`.`usuario_id` AS `mesero_id`, `u`.`nombre` AS `mesero`, count(`t`.`id`) AS `total_ventas`, sum(`t`.`total` - `t`.`propina`) AS `total_productos`, sum(`t`.`propina`) AS `total_propinas`, sum(`t`.`total`) AS `total_venta` FROM ((`tickets` `t` join `usuarios` `u` on(`t`.`usuario_id` = `u`.`id`)) join `ventas` `v` on(`t`.`venta_id` = `v`.`id`)) WHERE `v`.`estatus` = 'cerrada' GROUP BY `t`.`usuario_id`, `u`.`nombre` ;

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
-- AUTO_INCREMENT de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT de la tabla `corte_caja_historial`
--
ALTER TABLE `corte_caja_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `desglose_corte`
--
ALTER TABLE `desglose_corte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=225;

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
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT de la tabla `logs_accion`
--
ALTER TABLE `logs_accion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=628;

--
-- AUTO_INCREMENT de la tabla `log_asignaciones_mesas`
--
ALTER TABLE `log_asignaciones_mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `log_mesas`
--
ALTER TABLE `log_mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=384;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT de la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `usuario_ruta`
--
ALTER TABLE `usuario_ruta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT de la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- AUTO_INCREMENT de la tabla `venta_detalles_log`
--
ALTER TABLE `venta_detalles_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

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
