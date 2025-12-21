---para todos los usuarios---
INSERT IGNORE INTO usuario_ruta (usuario_id, ruta_id)
SELECT u.id, r.id
FROM usuarios u
JOIN rutas r ON r.id IN (1,11,13,15,34,35);

--- cajeros---
/* Solo para usuarios con rol admin o cajero:
   inserta (si no existe) las rutas: 2,4,6,7,14,28,33,30 */

INSERT IGNORE INTO usuario_ruta (usuario_id, ruta_id)
SELECT u.id, r.id
FROM usuarios u
JOIN rutas r ON r.id IN (2,4,6,7,14,28,33,30)
WHERE u.rol IN ('admin','cajero');

---meseros---
INSERT IGNORE INTO usuario_ruta (usuario_id, ruta_id)
SELECT u.id, r.id
FROM usuarios u
JOIN rutas r ON r.id = 10
WHERE u.rol IN ('mesero');
