-- Procedimiento 007: duplica un alumno existente con transacción.
-- El duplicado conserva grado y grupo, agrega " (copia)" al nombre
-- y genera una matrícula nueva con el formato COPIA-<origen>-<nuevo>.

DROP PROCEDURE IF EXISTS sp_duplicar_alumno;

CREATE PROCEDURE sp_duplicar_alumno(IN p_id_alumno INT)
BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE v_nombre VARCHAR(100);
    DECLARE v_matricula VARCHAR(20);
    DECLARE v_grado VARCHAR(20);
    DECLARE v_id_grupo INT;
    DECLARE v_nuevo_id INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_existe
      FROM alumnos
     WHERE IdAlumnos = p_id_alumno;

    IF v_existe = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Alumno no encontrado.';
    END IF;

    SELECT nombre, matricula, grado, IdGrupos
      INTO v_nombre, v_matricula, v_grado, v_id_grupo
      FROM alumnos
     WHERE IdAlumnos = p_id_alumno
     FOR UPDATE;

    INSERT INTO alumnos (nombre, matricula, grado, IdGrupos)
    VALUES (
        LEFT(CONCAT(v_nombre, ' (copia)'), 100),
        v_matricula,
        v_grado,
        v_id_grupo
    );

    SET v_nuevo_id = LAST_INSERT_ID();

    UPDATE alumnos
       SET matricula = LEFT(CONCAT('COPIA-', p_id_alumno, '-', v_nuevo_id), 20)
     WHERE IdAlumnos = v_nuevo_id;

    COMMIT;
END;
