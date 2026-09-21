-- Ejemplo de accesos de usuarios a clientes
-- admin_id: identificador del usuario/administrador
-- cli_id: identificador del cliente

-- Admin 1 tiene acceso a todos los clientes (dependerá de cuáles haya)
-- Admin 2 tiene acceso limitado a ciertos clientes

INSERT INTO admin_cliente (admin_id, cli_id) VALUES
(1, 1),
(1, 2),
(1, 3);

-- Ejemplo adicional: usuario 2 con acceso limitado
-- INSERT INTO admin_cliente (admin_id, cli_id) VALUES
-- (2, 1),
-- (2, 2);
