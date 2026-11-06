-- Procedimiento 004: actualización de alumno con transacción.

DROP PROCEDURE IF EXISTS sp_actualizar_alumno;

CREATE PROCEDURE sp_actualizar_alumno(
    IN p_id_alumno INT,
    IN p_nombre VARCHAR(100),
    IN p_matricula VARCHAR(20),
    IN p_grado VARCHAR(20),
    IN p_id_grupo INT
)
BEGIN
    DECLARE v_alumno_existe INT DEFAULT 0;
    DECLARE v_grupo_existe INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_alumno_existe
      FROM alumnos
     WHERE IdAlumnos = p_id_alumno;

    IF v_alumno_existe = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Alumno no encontrado.';
    END IF;

    SELECT COUNT(*) INTO v_grupo_existe
      FROM grupos
     WHERE IdGrupos = p_id_grupo;

    IF v_grupo_existe = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El grupo seleccionado no existe.';
    END IF;

    UPDATE alumnos
       SET nombre = p_nombre,
           matricula = p_matricula,
           grado = p_grado,
           IdGrupos = p_id_grupo
     WHERE IdAlumnos = p_id_alumno;

    COMMIT;
END;
