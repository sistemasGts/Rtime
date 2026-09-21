-- Inserciones para la tabla ad_modulo con los módulos del header actual
-- Asegúrate de que la tabla esté vacía o que el uso de mod_id explícito no choque con datos existentes.

INSERT INTO ad_modulo (mod_id, mod_padre, mod_nombre, mod_url, mod_icono, mod_orden, mod_estado) VALUES
(1, NULL, 'Panel', 'panel.php', NULL, 1, 1),
(2, NULL, 'Asistencia', NULL, NULL, 2, 1),
(3, 2, 'Reportes', NULL, NULL, 1, 1),
(4, 3, 'Reporte Primer y Último', 'asistencia_primeroultimo.php', NULL, 1, 1),
(5, 2, 'Gestionar Asistencia', NULL, NULL, 2, 1),
(6, 5, 'Gestionar turno', 'gestionar_turno.php', NULL, 1, 1),
(7, 5, 'Gestionar horario', 'gestionar_horario.php', NULL, 2, 1),
(8, 5, 'Gestionar absentismos', 'gestionar_absentismos.php', NULL, 3, 1),
(9, 5, 'Gestionar calendario', 'gestionar_calendario.php', NULL, 4, 1),
(10, 5, 'Gestionar feriados', 'gestionar_feriados.php', NULL, 5, 1),
(11, NULL, 'Empresas', 'gestionar_empresas.php', NULL, 3, 1),
(12, NULL, 'Gestión de huellas', 'gestionar_huellas.php', NULL, 4, 1),
(13, NULL, 'Gestionar', NULL, NULL, 5, 1),
(14, 13, 'Gestión de módulos', 'gestionar_modulos.php', NULL, 1, 1),
(15, 13, 'Gestión de usuarios', 'gestionar_usuarios.php', NULL, 2, 1),
(16, 13, 'Gestión de clientes', 'gestionar_clientes.php', NULL, 3, 1),
(17, 13, 'Gestión de planilla', 'gestionar_planilla.php', NULL, 4, 1);
