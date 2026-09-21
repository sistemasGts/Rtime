DELIMITER $$

DROP PROCEDURE IF EXISTS sp_generar_calendario$$

CREATE PROCEDURE sp_generar_calendario(
    IN pFechaInicio DATE,
    IN pFechaFin DATE
)
BEGIN
    DECLARE vFecha DATE;
    DECLARE vExiste INT DEFAULT 0;

    SELECT COUNT(*) INTO vExiste
    FROM calendario
    WHERE cal_dFecha BETWEEN pFechaInicio AND pFechaFin;

    IF vExiste > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se pueden volver a insertar esas fechas porque ya existen en el calendario.';
    END IF;

    SET vFecha = pFechaInicio;

    WHILE vFecha <= pFechaFin DO
        INSERT INTO calendario
        (
            cal_dFecha,
            cal_iAnio,
            cal_iMes,
            cal_iDia,
            cal_vcNombreMes,
            cal_vcNombreDia,
            cal_iNumeroSemana,
            cal_bFinSemana,
            cal_bFeriado,
            cal_bLaborable,
            cal_vcDescripcion
        )
        VALUES
        (
            vFecha,
            YEAR(vFecha),
            MONTH(vFecha),
            DAY(vFecha),

            ELT(MONTH(vFecha),
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ),

            ELT(DAYOFWEEK(vFecha),
                'Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'
            ),

            WEEK(vFecha,1),

            IF(DAYOFWEEK(vFecha) IN (1,7),1,0),

            0,

            IF(DAYOFWEEK(vFecha) = 1,0,1),

            NULL
        );

        SET vFecha = DATE_ADD(vFecha, INTERVAL 1 DAY);
    END WHILE;
END$$

DELIMITER ;
