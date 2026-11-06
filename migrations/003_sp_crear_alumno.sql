-- Procedimiento 003: alta de alumno con transacción.

DROP PROCEDURE IF EXISTS sp_crear_alumno;

CREATE PROCEDURE sp_crear_alumno(
    IN p_nombre VARCHAR(100),
    IN p_matricula VARCHAR(20),
    IN p_grado VARCHAR(20),
    IN p_id_grupo INT
)
BEGIN
    DECLARE v_grupo_existe INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_grupo_existe
      FROM grupos
     WHERE IdGrupos = p_id_grupo;

    IF v_grupo_existe = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El grupo seleccionado no existe.';
    END IF;

    INSERT INTO alumnos (nombre, matricula, grado, IdGrupos)
    VALUES (p_nombre, p_matricula, p_grado, p_id_grupo);

    COMMIT;
END;
