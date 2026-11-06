-- Procedimiento 006: actualización de grupo con transacción.

DROP PROCEDURE IF EXISTS sp_actualizar_grupo;

CREATE PROCEDURE sp_actualizar_grupo(
    IN p_id_grupo INT,
    IN p_grupo VARCHAR(50)
)
BEGIN
    DECLARE v_existe INT DEFAULT 0;
    DECLARE v_duplicado INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_existe
      FROM grupos
     WHERE IdGrupos = p_id_grupo;

    IF v_existe = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Grupo no encontrado.';
    END IF;

    SELECT COUNT(*) INTO v_duplicado
      FROM grupos
     WHERE grupo = p_grupo
       AND IdGrupos <> p_id_grupo;

    IF v_duplicado > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un grupo con ese nombre.';
    END IF;

    UPDATE grupos
       SET grupo = p_grupo
     WHERE IdGrupos = p_id_grupo;

    COMMIT;
END;
