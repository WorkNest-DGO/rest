-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 21-11-2025 a las 05:50:57
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
(12, 'Rollo Nano'),
(13, 'rollo horneado'),
(14, 'Alcohol');

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
(2, 'Serie Forestal', 2228);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalogo_promos`
--

CREATE TABLE `catalogo_promos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `visible_en_ticket` tinyint(1) NOT NULL DEFAULT 1,
  `tipo` enum('monto_fijo','porcentaje','bogo','combo','categoria_gratis') NOT NULL DEFAULT 'monto_fijo',
  `regla` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`regla`)),
  `tipo_venta` varchar(100) DEFAULT '''mesa''',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `catalogo_promos`
--

INSERT INTO `catalogo_promos` (`id`, `nombre`, `descripcion`, `monto`, `activo`, `visible_en_ticket`, `tipo`, `regla`, `tipo_venta`, `creado_en`) VALUES
(1, 'Té gratis', 'promo lunes Té de Jazmín (Litro) gratis', 0.00, 1, 1, 'categoria_gratis', '{\"id_producto\": 66,\"cantidad\":1}', 'mesa', '2025-09-08 04:29:14'),
(2, 'Entrada gratis', 'promo miércoles Entrada gratis', 0.00, 1, 1, 'categoria_gratis', '[{\"id_producto\": 35,\"categoria_id\":10},{\"id_producto\": 36,\"categoria_id\":10}]', 'mesa', '2025-09-08 04:34:53'),
(3, '2x1 Rollos horneados', 'promo martes 2 rollos por el precio de 1 ', 0.00, 1, 1, 'bogo', '{\"cantidad\": 2,\"categoria_id\":13}', 'mesa', '2025-09-08 04:39:56'),
(4, '3x2 horneados', 'promo jueves 3 rollos empanizados por el precio de 2 ', 0.00, 1, 1, 'bogo', '{\"cantidad\": 3,\"categoria_id\":9}', 'mesa', '2025-09-08 04:41:01'),
(5, '2 Té', '', 49.00, 1, 1, 'combo', '{\"cantidad\": 2,\"id_producto\":66}', 'llevar', '2025-11-14 05:00:08'),
(6, 'Promo 1 ', '2 rollos mas té 169 maky res, mar y tierra, chiquilin', 169.00, 1, 1, 'combo', '[{\"id_producto\": 15,\"cantidad\":1},{\"id_producto\": 12,\"cantidad\":1},{\"id_producto\": 16,\"cantidad\":1}]', 'llevar', '2025-11-14 05:27:24'),
(7, '2X1 alitas y té', '2x1 alitas + té jaszmin 219', 219.00, 1, 1, 'combo', '[{\"id_producto\": 105,\"cantidad\":2},{\"cantidad\": 1,\"id_producto\":66}]', 'llevar', '2025-11-14 05:27:24'),
(8, '2x1 boneless + té', '2x1 boneless + te jaszmin 219', 219.00, 1, 1, 'combo', '[{\"id_producto\": 104,\"cantidad\":2},{\"cantidad\": 1,\"id_producto\":66}]', 'llevar', '2025-11-14 05:32:05'),
(9, 'Promo 2', '3 x $209 en maky res, mar y tierra, chiquilin', 209.00, 1, 1, 'combo', '[{\"categoria_id\": 9,\"cantidad\":3}]', 'llevar', '2025-11-14 07:24:25');

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
-- Estructura de tabla para la tabla `cliente_venta`
--

CREATE TABLE `cliente_venta` (
  `id` int(11) NOT NULL,
  `idcliente` int(11) NOT NULL,
  `idventa` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16le COLLATE=utf16le_bin;

--
-- Volcado de datos para la tabla `cliente_venta`
--

INSERT INTO `cliente_venta` (`id`, `idcliente`, `idventa`) VALUES
(1, 13, 717);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cortes_almacen`
--

CREATE TABLE `cortes_almacen` (
  `id` int(11) NOT NULL,
  `fecha_inicio` datetime DEFAULT current_timestamp(),
  `fecha_fin` datetime DEFAULT NULL,
  `usuario_abre_id` int(11) DEFAULT NULL,
  `usuario_cierra_id` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf32 COLLATE=utf32_bin;

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
(109, 1, '2025-11-21 05:14:25', 2224, NULL, 0, NULL, NULL, NULL, 500.00);

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
(506, 109, 500.00, 1, 'efectivo', 10),
(507, 109, 1.00, 500, 'boucher', 12),
(508, 109, 1.00, 500, 'cheque', 13);

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
  `facturama_id` varchar(64) DEFAULT NULL,
  `ticket_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `folio` varchar(50) DEFAULT NULL,
  `serie` varchar(10) DEFAULT NULL,
  `uuid` varchar(64) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` varchar(5) NOT NULL DEFAULT 'PUE',
  `forma_pago` varchar(5) NOT NULL DEFAULT '03',
  `uso_cfdi` varchar(5) DEFAULT NULL,
  `fecha_emision` datetime DEFAULT current_timestamp(),
  `estado` enum('generada','cancelada') DEFAULT 'generada',
  `notas` text DEFAULT NULL,
  `xml_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
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
-- Estructura de tabla para la tabla `factura_tickets`
--

CREATE TABLE `factura_tickets` (
  `factura_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16le COLLATE=utf16le_bin;

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
(1, 500.00);

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
(1, 'Arroz', 'gramos', 100000.00, 'por_receta', 'ins_68717301313ad.jpg'),
(2, 'Alga', 'piezas', 100000.00, 'por_receta', 'ins_6871716a72681.jpg'),
(3, 'Salmón fresco', 'gramos', 100000.00, 'por_receta', 'ins_6871777fa2c56.png'),
(4, 'Refresco en lata', 'piezas', 100000.00, 'unidad_completa', 'ins_6871731d075cb.webp'),
(7, 'Surimi', 'gramos', 100000.00, 'uso_general', 'ins_688a521dcd583.jpg'),
(8, 'Tocino', 'gramos', 100000.00, 'uso_general', 'ins_688a4dc84c002.jpg'),
(9, 'Pollo', 'gramos', 100000.00, 'desempaquetado', 'ins_688a4e4bd5999.jpg'),
(10, 'Camarón', 'gramos', 100000.00, 'desempaquetado', 'ins_688a4f5c873c6.jpg'),
(11, 'Queso Chihuahua', 'gramos', 100000.00, 'unidad_completa', 'ins_688a4feca9865.jpg'),
(12, 'Philadelphia', 'gramos', 100000.00, 'uso_general', 'ins_688a504f9cb40.jpg'),
(13, 'Arroz blanco', 'gramos', 100000.00, 'por_receta', 'ins_689f82d674c65.jpg'),
(14, 'Carne', 'gramos', 100000.00, 'uso_general', 'ins_688a528d1261a.jpg'),
(15, 'Queso Amarillo', 'piezas', 100000.00, 'uso_general', 'ins_688a53246c1c2.jpg'),
(16, 'Ajonjolí', 'gramos', 100000.00, 'uso_general', 'ins_689f824a23343.jpg'),
(17, 'Panko', 'gramos', 100000.00, 'por_receta', 'ins_688a53da64b5f.jpg'),
(18, 'Salsa tampico', 'mililitros', 100000.00, 'no_controlado', 'ins_688a54cf1872b.jpg'),
(19, 'Anguila', 'oz', 100000.00, 'por_receta', 'ins_689f828638aa9.jpg'),
(20, 'BBQ', 'oz', 100000.00, 'no_controlado', 'ins_688a557431fce.jpg'),
(21, 'Serrano', 'gramos', 100000.00, 'uso_general', 'ins_688a55c66f09d.jpg'),
(22, 'Chile Morrón', 'gramos', 100000.00, 'por_receta', 'ins_688a5616e8f25.jpg'),
(23, 'Kanikama', 'gramos', 100000.00, 'por_receta', 'ins_688a5669e24a8.jpg'),
(24, 'Aguacate', 'gramos', 100000.00, 'por_receta', 'ins_689f8254c2e71.jpg'),
(25, 'Dedos de queso', 'pieza', 100000.00, 'unidad_completa', 'ins_688a56fda3221.jpg'),
(26, 'Mango', 'gramos', 100000.00, 'por_receta', 'ins_688a573c762f4.jpg'),
(27, 'Tostadas', 'pieza', 100000.00, 'uso_general', 'ins_688a57a499b35.jpg'),
(28, 'Papa', 'gramos', 100000.00, 'por_receta', 'ins_688a580061ffd.jpg'),
(29, 'Cebolla Morada', 'gramos', 100000.00, 'por_receta', 'ins_688a5858752a0.jpg'),
(30, 'Salsa de soya', 'mililitros', 100000.00, 'no_controlado', 'ins_688a58cc6cb6c.jpg'),
(31, 'Naranja', 'gramos', 100000.00, 'por_receta', 'ins_688a590bca275.jpg'),
(32, 'Chile Caribe', 'gramos', 100000.00, 'por_receta', 'ins_688a59836c32e.jpg'),
(33, 'Pulpo', 'gramos', 100000.00, 'por_receta', 'ins_688a59c9a1d0b.jpg'),
(34, 'Zanahoria', 'gramos', 100000.00, 'por_receta', 'ins_688a5a0a3a959.jpg'),
(35, 'Apio', 'gramos', 100000.00, 'por_receta', 'ins_688a5a52af990.jpg'),
(36, 'Pepino', 'gramos', 100000.00, 'uso_general', 'ins_688a5aa0cbaf5.jpg'),
(37, 'Masago', 'gramos', 100000.00, 'por_receta', 'ins_688a5b3f0dca6.jpg'),
(38, 'Nuez de la india', 'gramos', 100000.00, 'por_receta', 'ins_688a5be531e11.jpg'),
(39, 'Cátsup', 'mililitros', 100000.00, 'por_receta', 'ins_688a5c657eb83.jpg'),
(40, 'Atún fresco', 'gramos', 100000.00, 'por_receta', 'ins_688a5ce18adc5.jpg'),
(41, 'Callo almeja', 'gramos', 100000.00, 'por_receta', 'ins_688a5d28de8a5.jpg'),
(42, 'Calabacin', 'gramos', 100000.00, 'por_receta', 'ins_688a5d6b2bca1.jpg'),
(43, 'Fideo chino transparente', 'gramos', 100000.00, 'por_receta', 'ins_688a5dd3b406d.jpg'),
(44, 'Brócoli', 'gramos', 100000.00, 'por_receta', 'ins_688a5e2736870.jpg'),
(45, 'Chile de árbol', 'gramos', 100000.00, 'por_receta', 'ins_688a5e6f08ccd.jpg'),
(46, 'Pasta udon', 'gramos', 100000.00, 'por_receta', 'ins_688a5eb627f38.jpg'),
(47, 'Huevo', 'pieza', 100000.00, 'por_receta', 'ins_688a5ef9b575e.jpg'),
(48, 'Cerdo', 'gramos', 100000.00, 'por_receta', 'ins_688a5f3915f5e.jpg'),
(49, 'Masa para gyozas', 'pieza', 100000.00, 'por_receta', 'ins_688a5fae2e7f1.jpg'),
(50, 'Naruto', 'gramos', 100000.00, 'por_receta', 'ins_688a5ff57f62d.jpg'),
(51, 'Atún ahumado', 'gramos', 100000.00, 'por_receta', 'ins_68adcd62c5a19.jpg'),
(52, 'Cacahuate con salsa (salado)', 'gramos', 100000.00, 'por_receta', 'ins_68adcf253bd1d.jpg'),
(53, 'Calabaza', 'gramos', 100000.00, 'por_receta', 'ins_68add0ff781fb.jpg'),
(54, 'Camarón gigante para pelar', 'pieza', 100000.00, 'por_receta', 'ins_68add3264c465.jpg'),
(55, 'Cebolla', 'gramos', 100000.00, 'por_receta', 'ins_68add38beff59.jpg'),
(56, 'Chile en polvo', 'gramos', 100000.00, 'por_receta', 'ins_68add4a750a0e.jpg'),
(57, 'Coliflor', 'gramos', 100000.00, 'por_receta', 'ins_68add5291130e.jpg'),
(59, 'Dedos de surimi', 'pieza', 100000.00, 'unidad_completa', 'ins_68add5c575fbb.jpg'),
(60, 'Fideos', 'gramos', 100000.00, 'por_receta', 'ins_68add629d094b.jpg'),
(61, 'Fondo de res', 'mililitros', 100000.00, 'no_controlado', 'ins_68add68d317d5.jpg'),
(62, 'Gravy Naranja', 'oz', 100000.00, 'no_controlado', 'ins_68add7bb461b3.jpg'),
(63, 'Salsa Aguachil', 'oz', 100000.00, 'no_controlado', 'ins_68ae000034b31.jpg'),
(64, 'Julianas de zanahoria', 'gramos', 100000.00, 'por_receta', 'ins_68add82c9c245.jpg'),
(65, 'Limón', 'gramos', 100000.00, 'por_receta', 'ins_68add890ee640.jpg'),
(66, 'Queso Mix', 'gramos', 100000.00, 'uso_general', 'ins_68ade1625f489.jpg'),
(67, 'Morrón', 'gramos', 100000.00, 'por_receta', 'ins_68addcbc6d15a.jpg'),
(69, 'Pasta chukasoba', 'gramos', 100000.00, 'por_receta', 'ins_68addd277fde6.jpg'),
(70, 'Pasta frita', 'gramos', 100000.00, 'por_receta', 'ins_68addd91a005e.jpg'),
(71, 'Queso crema', 'gramos', 100000.00, 'uso_general', 'ins_68ade11cdadcb.jpg'),
(72, 'Refresco embotellado', 'pieza', 100000.00, 'unidad_completa', 'ins_68adfdd53f04e.jpg'),
(73, 'res', 'gramos', 100000.00, 'uso_general', 'ins_68adfe2e49580.jpg'),
(74, 'Rodajas de naranja', 'gramos', 100000.00, 'por_receta', 'ins_68adfeccd68d8.jpg'),
(75, 'Salmón', 'gramos', 100000.00, 'por_receta', 'ins_68adffa2a2db0.jpg'),
(76, 'Salsa de anguila', 'mililitros', 100000.00, 'no_controlado', 'ins_68ae005f1b3cd.jpg'),
(77, 'Salsa teriyaki (dulce)', 'mililitros', 100000.00, 'no_controlado', 'ins_68ae00c53121a.jpg'),
(78, 'Salsas orientales', 'mililitros', 100000.00, 'no_controlado', 'ins_68ae01341e7b1.jpg'),
(79, 'Shisimi', 'gramos', 100000.00, 'uso_general', 'ins_68ae018d22a63.jpg'),
(80, 'Siracha', 'mililitros', 100000.00, 'no_controlado', 'ins_68ae03413da26.jpg'),
(81, 'Tampico', 'mililitros', 100000.00, 'uso_general', 'ins_68ae03f65bd71.jpg'),
(82, 'Tortilla de harina', 'pieza', 100000.00, 'unidad_completa', 'ins_68ae04b46d24a.jpg'),
(83, 'Tostada', 'pieza', 100000.00, 'unidad_completa', 'ins_68ae05924a02a.jpg'),
(85, 'Yakimeshi mini', 'gramos', 100000.00, 'por_receta', 'ins_68ae061b1175b.jpg'),
(86, 'Sal con Ajo', 'pieza', 100000.00, 'por_receta', 'ins_68adff6dbf111.jpg'),
(87, 'Aderezo Chipotle', 'mililitros', 100000.00, 'por_receta', 'ins_68adcabeb1ee9.jpg'),
(88, 'Mezcla de Horneado', 'gramos', 100000.00, 'por_receta', 'ins_68addaa3e53f7.jpg'),
(89, 'Aderezo', 'gramos', 100000.00, 'uso_general', 'ins_68adcc0771a3c.jpg'),
(90, 'Camarón Empanizado', 'gramos', 100000.00, 'por_receta', 'ins_68add1de1aa0e.jpg'),
(91, 'Pollo Empanizado', 'gramos', 100000.00, 'por_receta', 'ins_68adde81c6be3.jpg'),
(92, 'Cebollín', 'gramos', 100000.00, 'por_receta', 'ins_68add3e38d04b.jpg'),
(93, 'Aderezo Cebolla Dul.', 'oz', 100000.00, 'uso_general', 'ins_68adcb8fa562e.jpg'),
(94, 'Camaron Enchiloso', 'gramos', 100000.00, 'por_receta', 'ins_68add2db69e2e.jpg'),
(95, 'Pastel chocoflan', 'pieza', 100000.00, 'unidad_completa', 'ins_68adddfa22fe2.jpg'),
(96, 'Pay de queso', 'pieza', 100000.00, 'unidad_completa', 'ins_68adde4fa8275.jpg'),
(97, 'Helado tempura', 'pieza', 100000.00, 'unidad_completa', 'ins_68add7e53c6fe.jpg'),
(98, 'Postre especial', 'pieza', 100000.00, 'unidad_completa', 'ins_68addee98fdf0.jpg'),
(99, 'Búfalo', 'mililitros', 100000.00, 'no_controlado', 'ins_68adce63dd347.jpg'),
(101, 'Corona 1/2', 'pieza', 100000.00, 'unidad_completa', 'ins_68add55a1e3b7.jpg'),
(102, 'Golden Light 1/2', 'pieza', 100000.00, 'unidad_completa', 'ins_68add76481f22.jpg'),
(103, 'Negra Modelo', 'pieza', 100000.00, 'unidad_completa', 'ins_68addc59c2ea9.jpg'),
(104, 'Modelo Especial', 'pieza', 100000.00, 'unidad_completa', 'ins_68addb9d59000.jpg'),
(105, 'Bud Light', 'pieza', 100000.00, 'unidad_completa', 'ins_68adcdf3295e8.jpg'),
(106, 'Stella Artois', 'pieza', 100000.00, 'unidad_completa', 'ins_68ae0397afb2f.jpg'),
(107, 'Ultra 1/2', 'pieza', 100000.00, 'unidad_completa', 'ins_68ae05466a8e2.jpg'),
(108, 'Michelob 1/2', 'pieza', 100000.00, 'unidad_completa', 'ins_68addb2d00c85.jpg'),
(109, 'Alitas de pollo', 'gramos', 100000.00, 'unidad_completa', 'ins_68adccf5a1147.jpg'),
(110, 'Ranch', 'mililitros', 100000.00, 'no_controlado', 'ins_68adfcddef7e3.jpg'),
(111, 'Buffalo', 'gramos', 100000.00, 'no_controlado', ''),
(112, 'Chichimi', 'gramos', 100000.00, 'no_controlado', 'ins_68add45bdb306.jpg'),
(113, 'Calpico', 'pieza', 100000.00, 'unidad_completa', 'ins_68add19570673.jpg'),
(114, 'Vaina de soja', 'gramos', 100000.00, 'uso_general', 'ins_68ae05de869d1.jpg'),
(115, 'Boneless', 'gramos', 100000.00, 'por_receta', 'ins_68adcdbb6b5b4.jpg'),
(116, 'Agua members', 'pieza', 100000.00, 'unidad_completa', 'ins_68adcc5feaee1.jpg'),
(117, 'Agua mineral', 'pieza', 100000.00, 'unidad_completa', 'ins_68adcca85ae2c.jpg'),
(118, 'Cilantro', 'gramos', 100000.00, 'por_receta', 'ins_68add4edab118.jpg'),
(119, 'Té de jazmin', 'mililitros', 100000.00, 'por_receta', 'ins_68ae0474dfc36.jpg'),
(120, 'bolsa camiseta 35x60', 'kilo', 100000.00, 'unidad_completa', ''),
(121, 'bolsa camiseta 25x50', 'kilo', 100000.00, 'unidad_completa', ''),
(122, 'bolsa camiseta 25x40', 'kilo', 100000.00, 'unidad_completa', ''),
(123, 'bolsa poliseda 15x25', 'rollo', 100000.00, 'unidad_completa', ''),
(124, 'bolsa rollo 20x30', 'rollo', 100000.00, 'unidad_completa', ''),
(125, 'bowls cpp1911-3', 'pieza', 100000.00, 'unidad_completa', ''),
(126, 'bowls cpp20', 'pieza', 100000.00, 'unidad_completa', ''),
(127, 'bowls cpp1911-3 tapa', 'pieza', 100000.00, 'unidad_completa', ''),
(128, 'bowls cpp20 tapa', 'pieza', 100000.00, 'unidad_completa', ''),
(129, 'baso termico 1l', 'piza', 100000.00, 'unidad_completa', ''),
(130, 'bisagra 22x22', 'pieza', 100000.00, 'unidad_completa', ''),
(131, 'servilleta', 'paquete', 100000.00, 'unidad_completa', ''),
(132, 'Papel aluminio 400', 'pieza', 100000.00, 'unidad_completa', ''),
(133, 'Vitafilim 14', 'rollo', 100000.00, 'unidad_completa', ''),
(134, 'guante vinil', 'caja', 100000.00, 'unidad_completa', ''),
(135, 'Popote 26cm', 'pieza', 100000.00, 'unidad_completa', ''),
(136, 'Bolsa papel x 100pz', 'paquete', 100000.00, 'unidad_completa', ''),
(137, 'rollo impresora mediano', 'rollo', 100000.00, 'unidad_completa', ''),
(138, 'rollo impresora grande', 'rollo', 100000.00, 'unidad_completa', ''),
(139, 'tenedor fantasy mediano 25pz', 'paquete', 100000.00, 'unidad_completa', ''),
(140, 'Bolsa basura 90x120 negra', 'bulto', 100000.00, 'unidad_completa', ''),
(141, 'Ts2', 'tira', 100000.00, 'unidad_completa', ''),
(142, 'Ts1', 'tira', 100000.00, 'unidad_completa', ''),
(143, 'TS200', 'tira', 100000.00, 'unidad_completa', ''),
(144, 'S100', 'tira', 100000.00, 'unidad_completa', ''),
(145, 'Pet 1l c/tapa', 'bulto', 100000.00, 'unidad_completa', ''),
(146, 'Pet 1/2l c/tapa', 'pieza', 100000.00, 'unidad_completa', ''),
(147, 'Cuchara mediana fantasy 50pz', 'paquete', 100000.00, 'unidad_completa', ''),
(148, 'Charola 8x8', 'pieza', 100000.00, 'unidad_completa', ''),
(149, 'Charola 6x6', 'pieza', 100000.00, 'unidad_completa', ''),
(150, 'Charola 8x8 negra', 'pieza', 100000.00, 'unidad_completa', ''),
(151, 'Charola 6x6 negra', 'pieza', 100000.00, 'unidad_completa', ''),
(152, 'Polipapel', 'kilo', 100000.00, 'unidad_completa', ''),
(153, 'Charola pastelera', 'pieza', 100000.00, 'unidad_completa', ''),
(154, 'Papel secante', 'pieza', 100000.00, 'unidad_completa', ''),
(155, 'Papel rollo higienico', 'pieza', 100000.00, 'unidad_completa', ''),
(156, 'Fabuloso 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(157, 'Desengrasante 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(158, 'Cloro 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(159, 'Iorizante 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(160, 'Windex 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(161, 'quitacochambre 1l', 'litro', 100000.00, 'unidad_completa', ''),
(162, 'Fibra metal', 'pieza', 100000.00, 'unidad_completa', ''),
(163, 'Esponja', 'pieza', 100000.00, 'unidad_completa', ''),
(164, 'Escoba', 'pieza', 100000.00, 'unidad_completa', ''),
(165, 'Recogedor', 'pieza', 100000.00, 'unidad_completa', ''),
(166, 'Trapeador', 'pieza', 100000.00, 'unidad_completa', ''),
(167, 'Cubeta 16l', 'pieza', 100000.00, 'unidad_completa', ''),
(168, 'Sanitas', 'paquete', 100000.00, 'unidad_completa', ''),
(169, 'Jabon polvo 9k', 'bulto', 100000.00, 'unidad_completa', ''),
(170, 'Shampoo trastes 20l', 'bidon', 100000.00, 'unidad_completa', ''),
(171, 'Jaladores', 'pieza', 100000.00, 'unidad_completa', ''),
(172, 'Cofia', 'pieza', 100000.00, 'unidad_completa', ''),
(173, 'Trapo', 'pieza', 100000.00, 'unidad_completa', ''),
(174, 'Sambal', 'mililitros', 100000.00, 'por_receta', ''),
(175, 'Lemon pepper', 'gramos', 100000.00, 'por_receta', ''),
(176, 'Consomé', 'gramos', 100000.00, 'por_receta', ''),
(177, 'Ejote', 'gramos', 100000.00, 'por_receta', ''),
(178, 'Chili bean', 'gramos', 100000.00, 'por_receta', ''),
(179, 'Ajinomoto', 'gramos', 100000.00, 'por_receta', ''),
(180, 'Salsa Yakimeshi', 'mililitros', 100000.00, 'no_controlado', ''),
(181, 'Papas a la francesa (porción kid)', 'porción', 100000.00, 'unidad_completa', ''),
(182, 'Cacahuate', 'gramos', 100000.00, 'por_receta', ''),
(183, 'Boneless (porción)', 'porción', 100000.00, 'unidad_completa', ''),
(184, 'Liner', 'pieza', 100000.00, 'unidad_completa', ''),
(185, 'Capeador General', 'mililitros', 100000.00, 'no_controlado', ''),
(186, 'Ajo', 'gramos', 100000.00, 'por_receta', ''),
(187, 'Jengibre', 'gramos', 100000.00, 'por_receta', ''),
(188, 'Hoisin', 'gramos', 100000.00, 'por_receta', ''),
(189, 'Col morada', 'gramos', 100000.00, 'por_receta', ''),
(190, 'Champiñon', 'kilo', 100000.00, 'uso_general', ''),
(191, 'Rabano', 'kilo', 100000.00, 'por_receta', '');

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
(1, 'Mesa 1', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(2, 'Mesa 2', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, 3),
(3, 'Mesa 3', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 4),
(4, 'Mesa 4', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(5, 'Mesa 5', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL),
(6, 'Mesa 6', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 3),
(7, 'Mesa 7', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(8, 'Mesa 8', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL),
(9, 'Mesa 9', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 3),
(10, 'Mesa 10', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(11, 'Mesa 11', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL),
(12, 'Mesa 12', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 3),
(13, 'Mesa 13', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(14, 'Mesa 14', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL),
(15, 'Mesa 15', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 3),
(16, 'Mesa 16', 'libre', 2, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(17, 'Mesa 17', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL),
(18, 'Mesa 18', 'libre', 4, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, 3),
(19, 'Mesa 19', 'libre', 6, NULL, 'Ala izquierda', NULL, 'ninguna', NULL, NULL, NULL, 1, 0, NULL),
(20, 'Mesa 20', 'libre', 4, NULL, 'Ala derecha', NULL, 'ninguna', NULL, NULL, NULL, 2, 0, NULL);

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
(24, 109, 1, 'deposito', 700.00, 's', '2025-11-21 05:29:22'),
(25, 109, 1, 'retiro', 200.00, 'd', '2025-11-21 05:29:28');

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
(4, 'Coca Cola', 29.00, 'Refresco embotellado', 1000000, 1, NULL, 1),
(5, 'Rollo California', 120.00, 'Salmón, arroz, alga nori', 1000000, 1, NULL, 3),
(6, 'Guamuchilito', 109.00, 'Surimi, camarón empanizado, salsa de anguila', 1000000, 1, NULL, 8),
(7, 'Guerra', 125.00, 'Camarón, ajonjolí, aguacate, salsa de anguila', 1000000, 1, NULL, 8),
(8, 'Triton Roll', 125.00, 'Philadelphia, pepino, aguacate, surimi, atún ahumado, anguila, siracha', 1000000, 1, 'prod_68add2ff11cc1.jpg', 8),
(9, 'Mechas', 139.00, 'Philadelphia, pepino, aguacate, camarón, ajonjolí, kanikama, camarón empanizado,limón, sirracha, anguila, shisimi', 1000000, 1, NULL, 8),
(10, 'Supremo', 135.00, 'Surimi, philadelphia, ajonjolí, tampico,  pollo capeado, salsa de anguila', 1000000, 1, NULL, 8),
(11, 'Roka Crunch Roll', 119.00, 'Philadelphia, pepino, aguacate, camarón, surimi empanizado, zanahoria rallada, salsa de anguila', 1000000, 1, NULL, 8),
(12, 'Mar y Tierra', 105.00, 'Rollo relleno de carne y camarón.', 1000000, 1, NULL, 9),
(13, 'Cielo, Mar y Tierra', 109.00, 'Pollo, carne, camarón', 1000000, 1, NULL, 9),
(14, '3 Quesos', 115.00, 'Rollo de camarón, carne, base, queso americano\n y gratinado con queso chihuahua.', 1000000, 1, 'prod_68adcf8c73757.jpg', 9),
(15, 'Chiquilin Roll', 115.00, 'Relleno de base (philadelphia, pepino y\n aguacate) Por fuera topping de camarón\n empanizado especial, bañado en salsa de anguila\n y ajonjolí.', 1000000, 1, NULL, 9),
(16, 'Maki roll res', 105.00, 'Rollo de 1 ingrediente a elegir (carne, tampico,\n pollo y camarón)', 1000000, 1, NULL, 9),
(17, 'Beef cheese', 119.00, 'Rollo de carne gratinado con queso spicy y\n ajonjolí.', 1000000, 1, NULL, 9),
(18, 'Cordon Blue', 115.00, 'Rollo relleno de carne y tocino forrado con\n philadelphia y gratinado con queso.', 1000000, 1, NULL, 9),
(19, 'Culichi Roll', 125.00, 'Rollo de carne con topping especial de tampico\n Tokyo empanizado coronado con camarón.', 1000000, 1, NULL, 9),
(20, 'Bacon Cheese', 125.00, 'Rollo de pollo por fuera gratinado con tocino.', 1000000, 1, 'prod_68add11ce2483.jpg', 9),
(21, 'Crunch Chicken', 125.00, 'Pollo empanizado, tocino, chile serrano, salsa bbq, salsa de anguila', 1000000, 1, NULL, 9),
(22, 'Kito', 119.00, 'Carne, tocino, queso, tampico', 1000000, 1, 'prod_68add69fe703c.jpg', 9),
(23, 'Norteño', 115.00, 'Camarón, tampico, queso, tocino, chile serrano', 1000000, 1, NULL, 9),
(24, 'Goloso Roll', 135.00, 'Res, pollo, tocino, queso o tampico', 1000000, 1, 'prod_68add37a08889.jpg', 9),
(25, 'Demon roll', 135.00, 'Res, tocino, toping demon (camarón enchiloso)', 1000000, 1, 'prod_68add435d40b1.jpg', 9),
(26, 'Nano Max', 245.00, 'Dedos de queso, dedos de surimi, carne, pollo, tocino, tampico, empanizado', 1000000, 1, NULL, 12),
(27, 'Nano XL', 325.00, 'Dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 1.5 kg', 1000000, 1, 'prod_68add3af36463.jpg', 12),
(28, 'Nano T-plus', 375.00, 'Dedos de queso, dedosde surimi, carne pollo, tocino, queso, tampico, 2 kg', 1000000, 1, NULL, 12),
(29, 'Chile Volcán', 85.00, 'Chile, 1 ingrediente a elegir, arroz, queso chihuahua,philadelphia', 1000000, 1, NULL, 10),
(30, 'Kushiagues', 75.00, 'Par de brochetas (camarón, pollo o surimi)', 1000000, 1, NULL, 10),
(31, 'Dedos de Queso', 69.00, 'Queso, empanizado (5 piezas)', 1000000, 1, NULL, 10),
(32, 'Tostada Culichi', 75.00, 'Tostada, camarón, pulpo, callo, pepino, cebolla morada, masago, chile serrano, chile en polvo, jugo de aguachile', 1000000, 1, 'prod_68add491b6f90.jpg', 10),
(33, 'Tostada tropical', 75.00, 'Tostada, atún, mango, camarón, callo, cebolla morada, chile en polvo, jugo de aguachile', 1000000, 1, NULL, 10),
(34, 'Empanada Horneada', 115.00, 'Tortilla de harina, carne, pollo, camarón,  mezcla de quesos, tampico, anguila y sirracha', 1000000, 1, NULL, 10),
(35, 'Rollitos', 75.00, 'Orden de 2 piezas, rellenos de philadelphia,\n queso chihuahua e ingrediente a elegir (res, pollo\n o camarón).', 1000000, 1, 'prod_68add227e7037.jpg', 10),
(36, 'Gyozas', 95.00, 'Orden con 6 piezas pequeñas (Pueden ser de\n philadelphia y camarón o de pollo y verduras)', 1000000, 1, NULL, 10),
(37, 'Papas a la francesa', 65.00, 'Papas a la francesa y cátsup ó aderezo especial', 1000000, 1, NULL, 10),
(38, 'Papas gajo', 75.00, 'Papas gajo y cátsup ó aderezo especial', 1000000, 1, NULL, 10),
(39, 'Ceviche Tokyo', 165.00, 'Cama de pepino, kanikama, camarón, aguacate, pulpo, jugo de aguachile', 1000000, 1, 'prod_68add2c342bb0.jpg', 3),
(40, 'Teriyaki krispy', 135.00, 'pollo empanizado, chile morrón, chile de arból, zanahoria, cebolla morada, cacahuate con salsa (salado)', 1000000, 1, NULL, 3),
(41, 'Teriyaki', 139.00, 'Ingrediente a elegir, salteado de cebolla, zanahoria, calabaza, brócoli y coliflor, salsa teriyaki (dulce)', 1000000, 1, NULL, 3),
(42, 'Pollo Mongol', 135.00, 'Pollo capeado, cebolla, zanahoria, apio, chile serrano, chile morrón, chile de arból, salsas orientales, montado en arroz blanco', 1000000, 1, 'prod_68add8fa7fb9e.jpg', 3),
(43, 'Chow Mein Especial', 155.00, 'Pasta frita, camarón, carne, pollo, vegetales, salsas orientales', 1000000, 1, 'prod_68adcfaa08c5a.jpg', 4),
(44, 'Chukasoba', 149.00, 'Camarón, pulpo, vegetales, pasta chukasoba', 1000000, 1, NULL, 4),
(45, 'Fideo Yurey', 165.00, 'Fideo chino transparente, julianas de zanahoria y apio, cebolla, chile caribe y morrón y la proteína de tu elección', 1000000, 1, NULL, 4),
(46, 'Udon spicy', 179.00, 'Julianas de zanahoria y cebolla, chile caribe, apio, chile de árbol, nuez de la india, ajonjolí, camarones capeados', 1000000, 1, 'prod_68add7d1cd5d9.jpg', 4),
(47, 'Orange Chiken Tokyo', 149.00, 'Pollo capeado (300gr), graby de naranja, pepino, zanahoria, rodajas de naranja, ajonjolíPollo capeando (300gr) rebosado con graby de\n naranja con zanahoria, pepino y rodajas de naranja\n y ajonjolí', 1000000, 1, NULL, 3),
(48, 'Udon Muchi', 125.00, 'Pasta udon, vegetales, camarón y pollo', 1000000, 1, NULL, 4),
(49, 'Tokyo ramen', 125.00, 'Pasta, vegetales, naruto, huevo, carne, camarón, fondo de res y cerdo', 1000000, 1, NULL, 4),
(50, 'Ramen Gran Meat', 125.00, 'Pasta, vegetales, trozos de carne sazonada con salsas orientales', 1000000, 1, NULL, 4),
(51, 'Ramen yasai', 115.00, 'Pasta, vegetales, fondo de res y cerdo', 1000000, 1, NULL, 4),
(52, 'Baby Ramen', 119.00, 'Pasta, vegetales, pollo a la plancha, salsas orientales, fondo de res y cerdo', 1000000, 1, NULL, 4),
(53, 'Cajun Ramen', 155.00, 'Fideos, vegetales, camarón gigante para pelar, fondo de res y cerdo, ajonjolí', 1000000, 1, NULL, 4),
(54, 'Gohan', 125.00, 'Arroz blanco, res y pollo, base de philadelphia y tampico con rodajas de aguacate, camarones empanizados, ajonjolí', 1000000, 1, NULL, 5),
(55, 'Gohan Krispy', 115.00, 'Arroz blanco, base de philadelphia, tampico y cubitos de aguacate, pollo y cebolla capeados, salsa de anguila, ajonjolí', 1000000, 1, 'prod_68add4bf039d2.jpg', 5),
(56, 'Yakimeshi', 115.00, 'Arroz frito, vegetales, carne, pollo y tocino, philadelphia, tampico, aguacate, ajonjolí', 1000000, 1, 'prod_68add0ace9c67.jpg', 5),
(57, 'Rollo Aguachile Especial', 125.00, 'Arroz frito, pollo empanizado, philadelphia, aguacate y tampico', 1000000, 1, 'prod_68add7b73652c.jpg', 5),
(58, 'Bomba', 115.00, 'Bola de arroz, res, pollo, philadelphia, queso chihuahua, tampico , empanizada y cubierta de salsa de anguila', 1000000, 1, 'prod_68add5bb666f3.jpg', 5),
(59, 'Menú kids 1', 79.00, '1/2 Rollo de pollo (6 piezas) y papas a la francesa', 1000000, 1, NULL, 3),
(60, 'Kid mini Yakimeshi', 85.00, 'Yakimeshi mini y papas a la francesa', 1000000, 1, NULL, 3),
(61, 'Menú Kids 3', 79.00, 'Dedos de queso (3 piezas) y papas a la francesa', 1000000, 1, NULL, 3),
(62, 'Chocoflan', 49.00, 'Porción de chocoflan', 1000000, 1, NULL, 2),
(63, 'Pay de Queso', 49.00, 'Porción de pay de queso', 1000000, 1, 'prod_68ae01fd0820f.jpg', 2),
(64, 'Helado Tempura', 79.00, 'Helado tempura', 1000000, 1, NULL, 2),
(65, 'Postre Especial', 79.00, NULL, 1000000, 1, 'prod_68ae00d2cd4af.jpg', 2),
(66, 'Té de Jazmín (Litro)', 33.00, 'Té verde con aroma a jazmín, servido en litro.', 1000000, 1, NULL, 1),
(67, 'Té de Jazmín (Refil)', 35.00, 'Té verde aromatizado con flores de jazmín.', 1000000, 1, NULL, 1),
(68, 'Limonada Natural', 35.00, 'Bebida de limón exprimido con agua y azúcar.', 1000000, 1, NULL, 1),
(69, 'Limonada Mineral', 38.00, 'Bebida de limón con agua mineral y azúcar.', 1000000, 1, NULL, 1),
(70, 'Naranjada Natural', 35.00, 'Bebida de jugo de naranja con agua y azúcar.', 1000000, 1, NULL, 1),
(71, 'Naranjada Mineral', 38.00, 'Refresco de naranja con agua mineral.', 1000000, 1, NULL, 1),
(72, 'Agua de Tamarindo', 35.00, 'Bebida dulce y ácida de tamarindo.', 1000000, 1, NULL, 1),
(73, 'Agua Mineral (355ml)', 35.00, 'Agua con gas en envase pequeño.', 1000000, 1, 'prod_68ae05aa8d01f.jpg', 1),
(74, 'Calpico', 35.00, 'Bebida japonesa dulce y láctea de yogur.', 1000000, 1, 'prod_68ae01959fac5.jpg', 1),
(75, 'Calpitamarindo', 39.00, NULL, 1000000, 1, NULL, 1),
(76, 'Refresco (335ml)', 29.00, 'Refresco embotellado', 1000000, 1, 'prod_68ae07bd9ef3c.jpg', 1),
(77, 'Aderezo de Chipotle', 10.00, 'Salsa cremosa picante de chipotle.', 1000000, 1, 'prod_68ae00b788642.jpg', 6),
(78, 'Aderezo de Cilantro', 15.00, 'Salsa cremosa con cilantro fresco.', 1000000, 1, NULL, 6),
(79, 'Salsa Sriracha', 10.00, 'Alsa picante de chile, ajo y vinagre.', 1000000, 1, 'prod_68ae083f538d5.jpg', 6),
(80, 'Jugo de Aguachile', 15.00, 'Salsa líquida de limón, chile y especias usada para marinar mariscos.', 1000000, 1, NULL, 6),
(81, 'Ranch', 15.00, 'Aderezo cremoso de hierbas y especias.', 1000000, 1, 'prod_68ae011cd828d.jpg', 6),
(82, 'Búfalo', 15.00, 'Salsa picante de chile y mantequilla.', 1000000, 1, 'prod_68ae0164e5f57.jpg', 6),
(83, 'BBQ', 15.00, 'Salsa dulce y ahumada para carnes.', 1000000, 1, NULL, 6),
(84, 'Soya Extra', 10.00, 'Salsa de soja concentrada o adicional', 1000000, 1, NULL, 6),
(85, 'Salsa de Anguila', 10.00, 'Salsa dulce y salada hecha con anguila y soja.', 1000000, 1, NULL, 6),
(86, 'Cebollitas o Chiles', 10.00, NULL, 1000000, 1, NULL, 6),
(87, 'Topping Horneado Especial', 20.00, 'Aderezo de chipotle, anguila y sriracha', 1000000, 1, NULL, 7),
(88, 'Topping Kanikama', 35.00, '(Ensalada de cangrejo)', 1000000, 1, NULL, 7),
(89, 'Topping Tampico', 15.00, '(Ensalada de surimi)', 1000000, 1, NULL, 7),
(90, 'Topping Demon', 35.00, 'Camarón, tocino, quesos, serrano y chichimi', 1000000, 1, NULL, 7),
(91, 'Topping Chiquilín', 30.00, 'Camarón empanizado, anguila y ajonjolí', 1000000, 1, NULL, 7),
(92, 'Gladiador Roll', 139.00, 'Por dentro philadelphia, pepino y aguacate. Por fuera trozos de pulpo, queso spicy, shishimi y cebolla, bañado en salsa de anguila y ajonjolí. Rollo natural.', 1000000, 1, NULL, 13),
(93, 'Güerito Roll', 145.00, 'Por dentro camarón. Forrado con philadelphia y manchego, bañado en aderezo de chipotle, coronado con tocino, caribe y bañado en salsa sriracha. Empanizado.', 1000000, 1, 'prod_68add1fe92388.jpg', 13),
(94, 'Ebby Especial Roll', 145.00, 'Por dentro base, forrado con tampico cheese, bañado en aderezo de chipotle y coronado con camarón mariposa, aguacate, anguila y ajonjolí. Empanizado.', 1000000, 1, NULL, 13),
(95, 'Pakun Roll', 135.00, 'Relleno de tocino, por fuera topping de pollo y queso spicy, zanahoria. Acompañado de salsa anguila. Rollo natural.', 1000000, 1, NULL, 13),
(96, 'Rorris Roll', 135.00, 'Camarón y caribe por dentro, topping de tampico cheese, aguacate y bañados en salsa de anguila y ajonjolí. Empanizado.', 1000000, 1, NULL, 13),
(97, 'Royal Roll', 139.00, 'Carne y tocino por dentro, con topping de pollo. Empanizado, bañado con aderezo de chipotle, salsa de anguila y ajonjolí.', 1000000, 1, 'prod_68adcf0749621.jpg', 13),
(98, 'Larry Roll', 155.00, 'Rollo relleno de camarón, forrado con salmón. Topping de surimi finamente picado, spicy, coronado con atún fresco y bañado en salsa de anguila y ajonjolí.', 1000000, 1, 'prod_68add883e8ce7.jpg', 11),
(99, 'Aguachile Especial Roll', 155.00, 'Rollo relleno de philadelphia, pepino y aguacate. Forrado de chile serrano finamente picado, coronado con un aguachile especial de camarón, pulpo, callo y aguacate.', 1000000, 1, NULL, 11),
(100, 'Mordick Roll', 145.00, 'Rollo relleno de tocino, montado doble con queso gratinado, mezcla de quesos spicy, coronado con camarones empanizados y bañado en salsa de anguila y ajonjolí.', 1000000, 1, 'prod_68add0ea0033c.jpg', 11),
(101, 'Maney Roll', 165.00, 'Relleno de philadelphia, pepino y aguacate. Forrado de aguacate fresco y topping con camarón, medallón de atún, callo, mango y cebolla morada. Acompañado de salsa aguachile. Rollo natural.', 1000000, 1, 'prod_68add31666451.jpg', 11),
(102, 'Onigiri', 59.00, '1 Pieza de triángulo de arroz blanco, con un toque ligero de philadelphia, forrado de alga, cubierto de ajonjolí y relleno opcional de pollo con verduras (col morada y zanahoria) o atún con aderezo especial de mayonesa y cebollín.', 1000000, 1, 'prod_68add35c53114.jpg', 3),
(103, 'Dumplings', 95.00, 'Orden de 6 piezas de dumplings, rellenos de carne molida de cerdo. Sazonados orientalmente y acompañado con salsa macha.', 1000000, 1, 'prod_68add5b64497c.jpg', 3),
(104, 'Boneless', 135.00, '250gr. De boneless con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel o mermelada de chipotle).', 1000000, 1, 'prod_68ae02330ae4d.jpg', 3),
(105, 'Alitas', 135.00, '250gr. De alitas con salsa a elegir (búfalo, bbq, mango habanero, mostaza miel ó mermelada de chipotle).', 1000000, 1, 'prod_68add564480c8.jpg', 3),
(106, 'Sopa Pho', 149.00, 'Rico fondo de pollo con vegetales, pechuga de pollo, fideos chinos y chile de árbol. Coronado con 4 piezas de dumplings.', 1000000, 1, 'prod_68adce4f32265.jpg', 3),
(107, 'Yummy Roll', 159.00, 'Alga por fuera, relleno de camarón, philadelphia, pepino y aguacate. Gratinado con queso spicy de la casa. Coronado con camarón, aguacate y bañado en salsa de anguila y ajonjolí.', 1000000, 1, 'prod_68add0c25fd53.jpg', 3),
(108, 'Cebolla Caramelizada', 10.00, 'Cebolla Caramelizada', 1000000, 1, NULL, 6),
(109, 'Kintaro', 102.00, 'Plato de sushi con atún graso picado toro y cebollín', 1000000, 1, NULL, NULL),
(110, 'Guamuchilito Especial', 119.00, 'Bebida preparada con jugo de guamúchil, combinada con alcohol, salsas y especias.', 1000000, 1, 'prod_68add0071ee2a.jpg', 11),
(111, 'Juny', 333.00, 'Juny', 1000000, 1, NULL, NULL),
(112, 'Pork Spicy', 122.00, 'Platillo de cerdo picante.', 1000000, 1, 'prod_68adcf27bc6a4.jpg', 8),
(120, 'Corona 1/2', 35.00, 'Cerveza helada', 1000000, 1, 'prod_68adff38848ad.jpg', 14),
(121, 'Corona Golden Light 1/2', 35.00, 'Cerveza Golden helada', 1000000, 1, 'prod_68adff1b2eb67.jpg', 1),
(122, 'Negra Modelo', 40.00, 'Cerveza negra helada', 1000000, 1, 'prod_68adffa389931.jpg', 1),
(123, 'Modelo Especial', 40.00, 'Cerveza helada', 1000000, 1, 'prod_68adfeeac5c57.jpg', 1),
(124, 'Bud Light', 35.00, 'Cerveza helada', 1000000, 1, 'prod_68ae0111e5f9d.jpg', 14),
(125, 'Stella Artois', 45.00, 'Cerveza Helada', 1000000, 1, 'prod_68ae081b893ed.jpg', 1),
(126, 'Ultra 1/2', 45.00, 'Cerveza helada', 1000000, 1, 'prod_68ae015b16f22.jpg', 1),
(127, 'Michelob 1/2', 45.00, 'Cerveza helada', 1000000, 1, NULL, 1),
(128, 'Vaso Chelado', 10.00, 'Vaso chelado', 1000000, 1, NULL, 6),
(129, 'Vaso Michelado', 15.00, 'Vaso michelado', 1000000, 1, NULL, 6),
(130, 'Vaso Clamato', 25.00, 'Vaso michelado', 1000000, 1, NULL, 6),
(131, 'Cheese fresse', 109.00, 'Concha de papa gajo sazonada, bañada en delicioso queso y tozos de tocino; al horno', 1000000, 1, 'prod_68add54669456.jpg', 3),
(132, 'Charola Kyoyu Suru', 189.00, 'Camaron capeado, aros de cebolla y gyosas de carne de cerdo, acompañado de delicioso dip especial de la casa y salsa oriental', 1000000, 1, 'prod_68add217dc164.jpg', 3),
(133, 'Alitas Nudz', 149.00, 'Deliciosos 300grs De alitas, bañadas en salsa dulce con chile, sabor cacahuate y ajonjolí', 1000000, 1, NULL, 3),
(134, 'Edamames', 79.00, 'Vaina de frijol de soja preparado con picante, soya,sal y limon en una cama de zanahoria', 1000000, 1, 'prod_68add3a313461.jpg', 3),
(135, 'Crispy Chesse', 99.00, 'Rollo de 6 a 7 pz relleno de carne, philadelphia, pepino y aguacate, empanizado, gratinado spicy y trozos de tocino frito.', 1000000, 1, NULL, 9),
(136, 'Chummy Roll', 99.00, 'Rollo de 6 a 7 pz relleno de philadelphia, pepino y aguacate, coronado con tampico y camarón Empanizado, bando en salsa de Anguila y ajonjoli.', 1000000, 1, NULL, 9),
(137, 'Pollo kai', 0.00, 'Pollo capeado con ejote, fécula, chili bean y sazón oriental', 1000000, 1, NULL, 3),
(138, 'Yakimeshi roka', 125.00, 'Arroz con tampico, philadelphia, verduras, boneless y aguacate', 1000000, 1, NULL, 5),
(139, 'Kushiages (Pollo)', 75.00, 'Par de brochetas de pollo', 1000000, 1, NULL, 10),
(140, 'Kushiages (Surimi)', 75.00, 'Par de brochetas de surimi', 1000000, 1, NULL, 10),
(141, 'Kushiages (Camarón)', 75.00, 'Par de brochetas de camarón con philadelphia', 1000000, 1, NULL, 10),
(142, 'Rollitos (Pollo)', 75.00, 'Orden de 2 pz con pollo', 1000000, 1, NULL, 10),
(143, 'Rollitos (Res)', 75.00, 'Orden de 2 pz con res', 1000000, 1, NULL, 10),
(144, 'Rollitos (Camarón)', 75.00, 'Orden de 2 pz con camarón', 1000000, 1, NULL, 10),
(9000, 'Cargo por plataforma:', 30.00, 'Cargo por uso de plataforma web para pedidos', 1000000, 1, NULL, 6),
(9001, 'ENVÍO – Repartidor casa', 30.00, 'Cargo por envío a domicilio (repartidor casa)', 1000000, 1, NULL, 6),
(9004, 'Gyozas Pollo', 95.00, 'Orden de 6 pzas rellenas de pollo, zanahoria, jengibre y ajo', 1000000, 1, NULL, 10),
(9005, 'Gyozas Camarón', 95.00, 'Orden de 6 pzas rellenas de camarón y philadelphia', 1000000, 1, NULL, 10),
(9006, 'volt', 25.00, '', 1000000, 1, NULL, NULL),
(9007, 'charola oro', 449.00, '', 1000000, 1, NULL, NULL),
(9008, 'charola plata', 339.00, '', 1000000, 1, NULL, NULL),
(9009, 'charola tokyo', 479.00, '', 1000000, 1, NULL, NULL),
(9010, 'agua natural', 20.00, '', 1000000, 1, NULL, NULL),
(9011, 'Ingrediente extra', 10.00, '', 1000000, 1, NULL, NULL),
(9012, 'apoyo', 5589.00, NULL, 10, 1, NULL, NULL),
(9013, 'apoyo2', 3790.00, NULL, 1, 1, NULL, NULL),
(9014, 'apoyo3', 2933.00, NULL, 1, 1, NULL, NULL),
(9015, 'desc', 1882.00, NULL, 1, 1, NULL, NULL),
(9016, 'Refresco Manzanita', 29.00, 'Refresco sabor manzana embotellado (335ml)', 1000000, 1, NULL, 1),
(9017, 'Refresco Sprite', 29.00, 'Refresco sabor lima-limón embotellado (335ml)', 1000000, 1, NULL, 1),
(9018, 'Refresco Fanta', 29.00, 'Refresco sabor naranja embotellado (335ml)', 1000000, 1, NULL, 1),
(9019, 'Refresco Coca-Cola Zero', 29.00, 'Refresco de cola sin azúcar embotellado (335ml)', 1000000, 1, NULL, 1),
(9020, 'Maki de Pollo', 105.00, 'Rollo de pollo estilo maki .', 1000000, 1, NULL, 9),
(9021, 'Maki de camaron', 105.00, 'Rollo de carne estilo maki.', 1000000, 1, NULL, 9),
(9022, 'Boneless Búfalo', 135.00, '250gr de boneless bañados en salsa búfalo.', 1000000, 1, NULL, 3),
(9023, 'Boneless Chipotle', 135.00, '250gr de boneless bañados en salsa de chipotle.', 1000000, 1, NULL, 3),
(9024, 'Boneless BBQ', 135.00, '250gr de boneless bañados en salsa BBQ.', 1000000, 1, NULL, 3),
(9025, 'Alitas Búfalo', 135.00, '250gr de alitas bañadas en salsa búfalo.', 1000000, 1, NULL, 3),
(9026, 'Alitas Chipotle', 135.00, '250gr de alitas bañadas en salsa de chipotle.', 1000000, 1, NULL, 3),
(9027, 'Alitas BBQ', 135.00, '250gr de alitas bañadas en salsa BBQ.', 1000000, 1, NULL, 3);

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
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `folio` int(11) NOT NULL,
  `serie_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `desc_des` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `monto_recibido` decimal(10,2) DEFAULT 0.00,
  `tipo_pago` enum('efectivo','boucher','cheque','plataforma','varios') DEFAULT 'efectivo',
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

INSERT INTO `tickets` (`id`, `venta_id`, `folio`, `serie_id`, `total`, `descuento`, `desc_des`, `fecha`, `usuario_id`, `monto_recibido`, `tipo_pago`, `sede_id`, `mesa_nombre`, `mesero_nombre`, `fecha_inicio`, `fecha_fin`, `tiempo_servicio`, `nombre_negocio`, `direccion_negocio`, `rfc_negocio`, `telefono_negocio`, `tipo_entrega`, `tarjeta_marca_id`, `tarjeta_banco_id`, `boucher`, `cheque_numero`, `cheque_banco_id`) VALUES
(491, 729, 2224, 2, 789.00, 289.00, NULL, '2025-11-21 05:15:22', 1, 500.00, 'efectivo', 1, 'Venta rápida', 'Administrador', NULL, '2025-11-21 05:15:22', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '6183222352', 'rapido', NULL, NULL, NULL, NULL, NULL),
(492, 730, 2225, 2, 789.00, 263.00, NULL, '2025-11-21 05:32:12', 38, 559.00, 'efectivo', 1, 'Mesa 5', 'beto', NULL, '2025-11-21 05:32:12', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '6183222352', 'mesa', NULL, NULL, NULL, NULL, NULL),
(493, 731, 2226, 2, 789.00, 289.00, NULL, '2025-11-21 05:46:03', 1, 500.00, 'boucher', 1, 'Venta rápida', 'Administrador', NULL, '2025-11-21 05:46:03', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '6183222352', 'rapido', 1, 3, '54445', NULL, NULL),
(494, 732, 2227, 2, 789.00, 289.00, NULL, '2025-11-21 05:46:50', 1, 500.00, 'cheque', 1, 'Venta rápida', 'Administrador', NULL, '2025-11-21 05:46:50', 0, 'Forestal', 'Blvd. Luis Donaldo Colosio #317, Fracc. La Forestal ', 'VEAJ9408188U9', '6183222352', 'rapido', NULL, NULL, NULL, '54445', 8);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ticket_descuentos`
--

CREATE TABLE `ticket_descuentos` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `tipo` enum('cortesia','porcentaje','monto_fijo','promocion') NOT NULL,
  `venta_detalle_id` int(11) DEFAULT NULL,
  `porcentaje` decimal(5,2) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `motivo` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `catalogo_promo_id` int(11) NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ticket_descuentos`
--

INSERT INTO `ticket_descuentos` (`id`, `ticket_id`, `tipo`, `venta_detalle_id`, `porcentaje`, `monto`, `motivo`, `usuario_id`, `catalogo_promo_id`, `creado_en`) VALUES
(77, 491, 'promocion', NULL, NULL, 289.00, NULL, 1, 9, '2025-11-20 22:15:22'),
(78, 492, 'promocion', NULL, NULL, 263.00, NULL, 38, 4, '2025-11-20 22:32:12'),
(79, 493, 'promocion', NULL, NULL, 289.00, NULL, 1, 9, '2025-11-20 22:46:03'),
(80, 494, 'promocion', NULL, NULL, 289.00, NULL, 1, 9, '2025-11-20 22:46:50');

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
(1289, 491, 15, 6, 115.00),
(1290, 491, 66, 3, 33.00),
(1291, 492, 15, 6, 115.00),
(1292, 492, 66, 3, 33.00),
(1293, 493, 15, 6, 115.00),
(1294, 493, 66, 3, 33.00),
(1295, 494, 15, 6, 115.00),
(1296, 494, 66, 3, 33.00);

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
  `propina_tarjeta` decimal(10,2) NOT NULL,
  `promocion_id` int(11) DEFAULT NULL,
  `promocion_descuento` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `fecha`, `mesa_id`, `repartidor_id`, `tipo_entrega`, `usuario_id`, `total`, `estatus`, `entregado`, `estado_entrega`, `fecha_asignacion`, `fecha_inicio`, `fecha_entrega`, `seudonimo_entrega`, `foto_entrega`, `corte_id`, `cajero_id`, `observacion`, `sede_id`, `propina_efectivo`, `propina_cheque`, `propina_tarjeta`, `promocion_id`, `promocion_descuento`) VALUES
(729, '2025-11-20 22:14:54', NULL, NULL, 'rapido', 1, 789.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 109, 1, '', 1, 0.00, 0.00, 0.00, 9, 289.00),
(730, '2025-11-20 22:30:43', 5, NULL, 'mesa', 38, 789.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 109, 1, '', 1, 74.00, 0.00, 0.00, 4, 263.00),
(731, '2025-11-20 22:45:41', NULL, NULL, 'rapido', 1, 789.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 109, 1, '', 1, 0.00, 0.00, 0.00, 9, 289.00),
(732, '2025-11-20 22:46:31', NULL, NULL, 'rapido', 1, 789.00, 'cerrada', 0, 'pendiente', NULL, NULL, NULL, NULL, NULL, 109, 1, '', 1, 0.00, 0.00, 0.00, 9, 289.00);

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
(2664, 729, 15, 6, 115.00, 0, '2025-11-20 22:14:54', NULL, 'pendiente', NULL),
(2665, 729, 66, 3, 33.00, 0, '2025-11-20 22:14:54', NULL, 'pendiente', NULL),
(2666, 730, 15, 6, 115.00, 0, '2025-11-20 22:30:43', NULL, 'pendiente', NULL),
(2667, 730, 66, 3, 33.00, 0, '2025-11-20 22:30:43', NULL, 'pendiente', NULL),
(2668, 731, 15, 6, 115.00, 0, '2025-11-20 22:45:41', NULL, 'pendiente', NULL),
(2669, 731, 66, 3, 33.00, 0, '2025-11-20 22:45:41', NULL, 'pendiente', NULL),
(2670, 732, 15, 6, 115.00, 0, '2025-11-20 22:46:31', NULL, 'pendiente', NULL),
(2671, 732, 66, 3, 33.00, 0, '2025-11-20 22:46:31', NULL, 'pendiente', NULL);

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta_promos`
--

CREATE TABLE `venta_promos` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `promo_id` int(11) NOT NULL,
  `descuento_aplicado` decimal(10,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `venta_promos`
--

INSERT INTO `venta_promos` (`id`, `venta_id`, `promo_id`, `descuento_aplicado`, `created_at`) VALUES
(25, 729, 9, 144.50, '2025-11-20 22:15:22'),
(26, 729, 5, 144.50, '2025-11-20 22:15:22'),
(27, 730, 4, 131.50, '2025-11-20 22:32:12'),
(28, 730, 1, 131.50, '2025-11-20 22:32:12'),
(29, 731, 9, 144.50, '2025-11-20 22:46:03'),
(30, 731, 5, 144.50, '2025-11-20 22:46:03'),
(31, 732, 9, 144.50, '2025-11-20 22:46:50'),
(32, 732, 5, 144.50, '2025-11-20 22:46:50');

--
-- Índices para tablas volcadas
--

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
-- Indices de la tabla `catalogo_folios`
--
ALTER TABLE `catalogo_folios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `catalogo_promos`
--
ALTER TABLE `catalogo_promos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cp_activo_visible` (`activo`,`visible_en_ticket`),
  ADD KEY `idx_cp_tipo` (`tipo`);

--
-- Indices de la tabla `catalogo_tarjetas`
--
ALTER TABLE `catalogo_tarjetas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cliente_venta`
--
ALTER TABLE `cliente_venta`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `cortes_almacen`
--
ALTER TABLE `cortes_almacen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_abre_id` (`usuario_abre_id`),
  ADD KEY `usuario_cierra_id` (`usuario_cierra_id`);

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
  ADD KEY `idx_fact_cliente` (`cliente_id`),
  ADD KEY `idx_uuid` (`uuid`);

--
-- Indices de la tabla `factura_detalles`
--
ALTER TABLE `factura_detalles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_fd_ticketdet` (`ticket_detalle_id`),
  ADD KEY `idx_fd_fact` (`factura_id`);

--
-- Indices de la tabla `factura_tickets`
--
ALTER TABLE `factura_tickets`
  ADD PRIMARY KEY (`factura_id`,`ticket_id`),
  ADD UNIQUE KEY `uq_ticket_unico` (`ticket_id`);

--
-- Indices de la tabla `fondo`
--
ALTER TABLE `fondo`
  ADD PRIMARY KEY (`usuario_id`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
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
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_productos_categoria` (`categoria_id`);

--
-- Indices de la tabla `repartidores`
--
ALTER TABLE `repartidores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_tickets_serie_folio` (`serie_id`,`folio`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `fk_tickets_sede` (`sede_id`),
  ADD KEY `fk_ticket_tarjeta_marca` (`tarjeta_marca_id`),
  ADD KEY `fk_ticket_tarjeta_banco` (`tarjeta_banco_id`),
  ADD KEY `fk_ticket_cheque_banco` (`cheque_banco_id`);

--
-- Indices de la tabla `ticket_descuentos`
--
ALTER TABLE `ticket_descuentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `venta_detalle_id` (`venta_detalle_id`);

--
-- Indices de la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `producto_id` (`producto_id`);

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
-- Indices de la tabla `venta_promos`
--
ALTER TABLE `venta_promos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_venta_promos_venta_id` (`venta_id`),
  ADD KEY `idx_venta_promos_promo_id` (`promo_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalogo_bancos`
--
ALTER TABLE `catalogo_bancos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `catalogo_categorias`
--
ALTER TABLE `catalogo_categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `catalogo_folios`
--
ALTER TABLE `catalogo_folios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `catalogo_promos`
--
ALTER TABLE `catalogo_promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `catalogo_tarjetas`
--
ALTER TABLE `catalogo_tarjetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `cliente_venta`
--
ALTER TABLE `cliente_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cortes_almacen`
--
ALTER TABLE `cortes_almacen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `corte_caja`
--
ALTER TABLE `corte_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT de la tabla `corte_caja_historial`
--
ALTER TABLE `corte_caja_historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `desglose_corte`
--
ALTER TABLE `desglose_corte`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=509;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `factura_detalles`
--
ALTER TABLE `factura_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

--
-- AUTO_INCREMENT de la tabla `mesas`
--
ALTER TABLE `mesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `movimientos_caja`
--
ALTER TABLE `movimientos_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9028;

--
-- AUTO_INCREMENT de la tabla `repartidores`
--
ALTER TABLE `repartidores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=495;

--
-- AUTO_INCREMENT de la tabla `ticket_descuentos`
--
ALTER TABLE `ticket_descuentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT de la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1297;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=733;

--
-- AUTO_INCREMENT de la tabla `venta_detalles`
--
ALTER TABLE `venta_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2672;

--
-- AUTO_INCREMENT de la tabla `venta_detalles_cancelados`
--
ALTER TABLE `venta_detalles_cancelados`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=173;

--
-- AUTO_INCREMENT de la tabla `venta_detalles_log`
--
ALTER TABLE `venta_detalles_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=931;

--
-- AUTO_INCREMENT de la tabla `venta_promos`
--
ALTER TABLE `venta_promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cortes_almacen`
--
ALTER TABLE `cortes_almacen`
  ADD CONSTRAINT `cortes_almacen_ibfk_1` FOREIGN KEY (`usuario_abre_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `cortes_almacen_ibfk_2` FOREIGN KEY (`usuario_cierra_id`) REFERENCES `usuarios` (`id`);

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
-- Filtros para la tabla `factura_tickets`
--
ALTER TABLE `factura_tickets`
  ADD CONSTRAINT `fk_ft_factura` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ft_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `fondo`
--
ALTER TABLE `fondo`
  ADD CONSTRAINT `fondo_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

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
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_ticket_cheque_banco` FOREIGN KEY (`cheque_banco_id`) REFERENCES `catalogo_bancos` (`id`),
  ADD CONSTRAINT `fk_ticket_tarjeta_banco` FOREIGN KEY (`tarjeta_banco_id`) REFERENCES `catalogo_bancos` (`id`),
  ADD CONSTRAINT `fk_ticket_tarjeta_marca` FOREIGN KEY (`tarjeta_marca_id`) REFERENCES `catalogo_tarjetas` (`id`),
  ADD CONSTRAINT `fk_tickets_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  ADD CONSTRAINT `fk_tickets_serie` FOREIGN KEY (`serie_id`) REFERENCES `catalogo_folios` (`id`),
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ticket_descuentos`
--
ALTER TABLE `ticket_descuentos`
  ADD CONSTRAINT `fk_td_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  ADD CONSTRAINT `fk_td_vd` FOREIGN KEY (`venta_detalle_id`) REFERENCES `venta_detalles` (`id`);

--
-- Filtros para la tabla `ticket_detalles`
--
ALTER TABLE `ticket_detalles`
  ADD CONSTRAINT `ticket_detalles_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  ADD CONSTRAINT `ticket_detalles_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

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

--
-- Filtros para la tabla `venta_promos`
--
ALTER TABLE `venta_promos`
  ADD CONSTRAINT `venta_promos_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  ADD CONSTRAINT `venta_promos_ibfk_2` FOREIGN KEY (`promo_id`) REFERENCES `catalogo_promos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
