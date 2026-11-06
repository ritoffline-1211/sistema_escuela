-- Procedimiento 005: alta de grupo con transacción.

DROP PROCEDURE IF EXISTS sp_crear_grupo;

CREATE PROCEDURE sp_crear_grupo(IN p_grupo VARCHAR(50))
BEGIN
    DECLARE v_existe INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_existe
      FROM grupos
     WHERE grupo = p_grupo;

    IF v_existe > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe un grupo con ese nombre.';
    END IF;

    INSERT INTO grupos (grupo) VALUES (p_grupo);

    COMMIT;
END;
