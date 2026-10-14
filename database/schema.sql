-- ---------------------------------------------------------------------------
-- Hospital Materno Rosa
-- Esquema de base de datos (basado en database.dbml)
-- MySQL / MariaDB - InnoDB - utf8mb4
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `adjuntos_archivos`;
DROP TABLE IF EXISTS `horarios_medico`;
DROP TABLE IF EXISTS `detalle_historia`;
DROP TABLE IF EXISTS `citas`;
DROP TABLE IF EXISTS `historia`;
DROP TABLE IF EXISTS `medicos`;
DROP TABLE IF EXISTS `pacientes`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `especialidades`;
DROP TABLE IF EXISTS `roles`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id_rol` INT NOT NULL AUTO_INCREMENT,
    `nombre_rol` VARCHAR(50) NOT NULL,
    `descripcion` VARCHAR(255) NULL,
    PRIMARY KEY (`id_rol`),
    UNIQUE KEY `roles_nombre_rol_unique` (`nombre_rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- especialidades
-- ---------------------------------------------------------------------------
CREATE TABLE `especialidades` (
    `id_especialidad` INT NOT NULL AUTO_INCREMENT,
    `nombre_especialidad` VARCHAR(100) NOT NULL,
    `descripcion` TEXT NULL,
    PRIMARY KEY (`id_especialidad`),
    UNIQUE KEY `especialidades_nombre_especialidad_unique` (`nombre_especialidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- usuarios
-- ---------------------------------------------------------------------------
CREATE TABLE `usuarios` (
    `id_usuario` INT NOT NULL AUTO_INCREMENT,
    `nacionalidad` VARCHAR(1) NOT NULL DEFAULT 'V',
    `cedula` VARCHAR(15) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `id_rol` INT NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'Activo',
    -- EXTENSION DEL PROYECTO: nombre y correo para los usuarios que no son
    -- pacientes ni medicos (Administrador / Tecnico) y para el alta de personal.
    `nombres` VARCHAR(100) NULL,
    `apellidos` VARCHAR(100) NULL,
    `correo` VARCHAR(150) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_usuario`),
    UNIQUE KEY `usuarios_cedula_unique` (`cedula`),
    UNIQUE KEY `usuarios_correo_unique` (`correo`),
    KEY `fk_usuarios_id_rol_roles` (`id_rol`),
    CONSTRAINT `fk_usuarios_id_rol_roles`
        FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- pacientes
-- ---------------------------------------------------------------------------
CREATE TABLE `pacientes` (
    `id_paciente` INT NOT NULL AUTO_INCREMENT,
    `id_usuario` INT NOT NULL,
    `num_historia` VARCHAR(20) NULL,
    `nombres` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(100) NOT NULL,
    `fecha_nacimiento` DATE NOT NULL,
    `estado_civil` VARCHAR(30) NULL,
    `ocupacion` VARCHAR(100) NULL,
    `direccion` TEXT NULL,
    `telefono` VARCHAR(20) NULL,
    `contacto_emergencia_nombre` VARCHAR(150) NULL,
    `contacto_emergencia_telefono` VARCHAR(20) NULL,
    PRIMARY KEY (`id_paciente`),
    UNIQUE KEY `pacientes_id_usuario_unique` (`id_usuario`),
    UNIQUE KEY `pacientes_num_historia_unique` (`num_historia`),
    CONSTRAINT `fk_pacientes_id_usuario_usuarios`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- medicos
-- ---------------------------------------------------------------------------
CREATE TABLE `medicos` (
    `id_medico` INT NOT NULL AUTO_INCREMENT,
    `id_usuario` INT NOT NULL,
    `id_especialidad` INT NOT NULL,
    `nombres` VARCHAR(100) NOT NULL,
    `apellidos` VARCHAR(100) NOT NULL,
    `num_colegiatura` VARCHAR(50) NULL,
    `telefono` VARCHAR(20) NULL,
    PRIMARY KEY (`id_medico`),
    UNIQUE KEY `medicos_id_usuario_unique` (`id_usuario`),
    UNIQUE KEY `medicos_num_colegiatura_unique` (`num_colegiatura`),
    KEY `fk_medicos_id_especialidad_especialidades` (`id_especialidad`),
    CONSTRAINT `fk_medicos_id_usuario_usuarios`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_medicos_id_especialidad_especialidades`
        FOREIGN KEY (`id_especialidad`) REFERENCES `especialidades` (`id_especialidad`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- historia (antecedentes gineco-obstetricos)
-- ---------------------------------------------------------------------------
CREATE TABLE `historia` (
    `id_antecedente` INT NOT NULL AUTO_INCREMENT,
    `id_paciente` INT NOT NULL,
    `menarquia` INT NULL,
    `sexarquia` INT NULL,
    `ciclos_menstruales` VARCHAR(50) NULL,
    `gestas` INT NOT NULL DEFAULT 0,
    `partos` INT NOT NULL DEFAULT 0,
    `abortos` INT NOT NULL DEFAULT 0,
    `cesareas` INT NOT NULL DEFAULT 0,
    `fecha_ultima_regla` DATE NULL,
    `fecha_ultima_citologia` DATE NULL,
    `metodo_anticonceptivo` VARCHAR(100) NULL,
    `observaciones` TEXT NULL,
    PRIMARY KEY (`id_antecedente`),
    UNIQUE KEY `historia_id_paciente_unique` (`id_paciente`),
    CONSTRAINT `fk_historia_id_paciente_pacientes`
        FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- citas
-- ---------------------------------------------------------------------------
CREATE TABLE `citas` (
    `id_cita` INT NOT NULL AUTO_INCREMENT,
    `id_paciente` INT NOT NULL,
    `id_medico` INT NOT NULL,
    `fecha_cita` DATE NOT NULL,
    `hora_cita` TIME NOT NULL,
    `estado` VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
    `motivo_consulta` VARCHAR(255) NULL,
    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_cita`),
    KEY `fk_citas_id_paciente_pacientes` (`id_paciente`),
    KEY `fk_citas_id_medico_medicos` (`id_medico`),
    CONSTRAINT `fk_citas_id_paciente_pacientes`
        FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_citas_id_medico_medicos`
        FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- detalle_historia (consulta medica)
-- ---------------------------------------------------------------------------
CREATE TABLE `detalle_historia` (
    `id_consulta` INT NOT NULL AUTO_INCREMENT,
    `id_cita` INT NOT NULL,
    `id_paciente` INT NOT NULL,
    `id_medico` INT NOT NULL,
    `tipo_consulta` VARCHAR(50) NOT NULL COMMENT 'Primera Vez / Apertura, Control / Evolucion',
    `enfermedad_actual` TEXT NULL,
    `examen_fisico` TEXT NULL,
    `diagnostico` TEXT NOT NULL,
    `tratamiento_plan` TEXT NULL,
    `notas_evolucion` TEXT NULL,
    `fecha_consulta` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_consulta`),
    UNIQUE KEY `detalle_historia_id_cita_unique` (`id_cita`),
    KEY `fk_detalle_historia_id_paciente_pacientes` (`id_paciente`),
    KEY `fk_detalle_historia_id_medico_medicos` (`id_medico`),
    CONSTRAINT `fk_detalle_historia_id_cita_citas`
        FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_detalle_historia_id_paciente_pacientes`
        FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_detalle_historia_id_medico_medicos`
        FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- adjuntos_archivos
-- ---------------------------------------------------------------------------
CREATE TABLE `adjuntos_archivos` (
    `id_adjunto` INT NOT NULL AUTO_INCREMENT,
    `id_consulta` INT NOT NULL,
    `nombre_archivo` VARCHAR(255) NOT NULL,
    `ruta_archivo` VARCHAR(255) NOT NULL,
    `tipo_archivo` VARCHAR(50) NULL,
    `descripcion` VARCHAR(255) NULL,
    `subido_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_adjunto`),
    KEY `fk_adjuntos_archivos_id_consulta_historia` (`id_consulta`),
    CONSTRAINT `fk_adjuntos_archivos_id_consulta_historia`
        FOREIGN KEY (`id_consulta`) REFERENCES `detalle_historia` (`id_consulta`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- EXTENSION DEL PROYECTO (no incluida en database.dbml original)
-- Requerida por la vista "Horarios y Cupos" del modulo Administrador y por el
-- calculo de disponibilidad en "Agendar Cita" del modulo Paciente.
-- ---------------------------------------------------------------------------
CREATE TABLE `horarios_medico` (
    `id_horario` INT NOT NULL AUTO_INCREMENT,
    `id_medico` INT NOT NULL,
    `dia_semana` VARCHAR(15) NOT NULL COMMENT 'Lunes, Martes, Miercoles, Jueves, Viernes, Sabado, Domingo',
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `cupos_max` INT NOT NULL DEFAULT 10,
    `consultorio` VARCHAR(50) NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_horario`),
    UNIQUE KEY `horarios_medico_unico` (`id_medico`, `dia_semana`, `hora_inicio`),
    CONSTRAINT `fk_horarios_medico_id_medico_medicos`
        FOREIGN KEY (`id_medico`) REFERENCES `medicos` (`id_medico`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
