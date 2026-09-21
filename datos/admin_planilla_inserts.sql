-- Ejemplo de accesos de usuarios a planillas
-- admin_id: identificador del usuario/administrador
-- conpla_id: identificador de la planilla

-- Admin 1 con acceso a algunas planillas
INSERT INTO admin_planilla (admin_id, conpla_id) VALUES
(1, 10),
(1, 11),
(1, 12);

-- Admin 2 con acceso a otras planillas
INSERT INTO admin_planilla (admin_id, conpla_id) VALUES
(2, 15),
(2, 16);
