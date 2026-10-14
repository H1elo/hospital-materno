-- ---------------------------------------------------------------------------
-- Hospital Materno Rosa - Datos de demostracion
-- Todas las cuentas usan la clave: 123456
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

DELETE FROM `adjuntos_archivos`;
DELETE FROM `detalle_historia`;
DELETE FROM `horarios_medico`;
DELETE FROM `citas`;
DELETE FROM `historia`;
DELETE FROM `medicos`;
DELETE FROM `pacientes`;
DELETE FROM `usuarios`;
DELETE FROM `especialidades`;
DELETE FROM `roles`;

-- ---------------------------------------------------------------------------
-- roles
-- ---------------------------------------------------------------------------
INSERT INTO `roles` (`id_rol`, `nombre_rol`, `descripcion`) VALUES
    (1, 'Paciente', 'Paciente registrado en el hospital'),
    (2, 'Medico', 'Personal medico con acceso a historias clinicas'),
    (3, 'Tecnico de Registro', 'Personal encargado del registro y estadisticas'),
    (4, 'Administrador', 'Gestion de personal, horarios y cupos');

-- ---------------------------------------------------------------------------
-- especialidades
-- ---------------------------------------------------------------------------
INSERT INTO `especialidades` (`id_especialidad`, `nombre_especialidad`, `descripcion`) VALUES
    (1, 'Ginecologia y Obstetricia', 'Salud femenina, embarazo, parto y puerperio'),
    (2, 'Neonatologia', 'Atencion integral del recien nacido'),
    (3, 'Pediatria', 'Seguimiento del crecimiento y desarrollo infantil'),
    (4, 'Medicina General', 'Consulta general y referencia'),
    (5, 'Ecografia y Diagnostico', 'Estudios de imagen obstetrica y ginecologica');

-- ---------------------------------------------------------------------------
-- usuarios (clave: 123456)
-- ---------------------------------------------------------------------------
INSERT INTO `usuarios`
    (`id_usuario`, `nacionalidad`, `cedula`, `password_hash`, `id_rol`, `estado`,
     `nombres`, `apellidos`, `correo`)
VALUES
    (1, 'V', '10000001', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 4, 'Activo',
     'Ana', 'Rivas', 'ana.rivas@hospitalmaternorosa.gob'),
    (2, 'V', '20000002', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 2, 'Activo',
     'Carmen', 'Lopez', 'carmen.lopez@hospitalmaternorosa.gob'),
    (3, 'V', '20000003', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 2, 'Activo',
     'Andres', 'Vargas', 'andres.vargas@hospitalmaternorosa.gob'),
    (4, 'V', '30000004', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 3, 'Activo',
     'Luis', 'Mendoza', 'luis.mendoza@hospitalmaternorosa.gob'),
    (5, 'V', '40000005', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 1, 'Activo',
     'Maria Elena', 'Perez Garcia', 'maria.perez@correo.com'),
    (6, 'V', '40000006', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 1, 'Activo',
     'Rosa Amelia', 'Martinez Diaz', 'rosa.martinez@correo.com'),
    (7, 'V', '40000007', '$2y$12$H2eIzB.NoHe4nL.Bg5Wpq.7i0mI6pfvuuBPVG0lwPNKD8EqlZKtsK', 1, 'Activo',
     'Ana Carolina', 'Torres Leon', 'ana.torres@correo.com');

-- ---------------------------------------------------------------------------
-- pacientes
-- ---------------------------------------------------------------------------
INSERT INTO `pacientes`
    (`id_paciente`, `id_usuario`, `num_historia`, `nombres`, `apellidos`, `fecha_nacimiento`,
     `estado_civil`, `ocupacion`, `direccion`, `telefono`,
     `contacto_emergencia_nombre`, `contacto_emergencia_telefono`)
VALUES
    (1, 5, 'HC-0001', 'Maria Elena', 'Perez Garcia', '1994-05-12', 'Casada', 'Docente',
     'Av. Libertador, Res. Los Rosales, Apt 3-B', '0414-1234567',
     'Jose Perez', '0414-7654321'),
    (2, 6, 'HC-0002', 'Rosa Amelia', 'Martinez Diaz', '1988-11-03', 'Soltera', 'Comerciante',
     'Calle Sucre, Casa 45, Sector Centro', '0424-2345678',
     'Luis Martinez', '0424-8765432'),
    (3, 7, 'HC-0003', 'Ana Carolina', 'Torres Leon', '2000-02-20', 'Soltera', 'Estudiante',
     'Urb. Las Palmas, Calle 7, Casa 12', '0412-3456789',
     'Marta Leon', '0412-9876543');

-- ---------------------------------------------------------------------------
-- medicos
-- ---------------------------------------------------------------------------
INSERT INTO `medicos`
    (`id_medico`, `id_usuario`, `id_especialidad`, `nombres`, `apellidos`, `num_colegiatura`, `telefono`)
VALUES
    (1, 2, 1, 'Carmen', 'Lopez', 'MPPS-12345', '0414-1112233'),
    (2, 3, 2, 'Andres', 'Vargas', 'MPPS-23456', '0414-4445566');

-- ---------------------------------------------------------------------------
-- historia (antecedentes gineco-obstetricos)
-- ---------------------------------------------------------------------------
INSERT INTO `historia`
    (`id_antecedente`, `id_paciente`, `menarquia`, `sexarquia`, `ciclos_menstruales`,
     `gestas`, `partos`, `abortos`, `cesareas`, `fecha_ultima_regla`,
     `fecha_ultima_citologia`, `metodo_anticonceptivo`, `observaciones`)
VALUES
    (1, 1, 12, 18, 'Regulares / 28 dias', 2, 1, 0, 0, DATE_SUB(CURDATE(), INTERVAL 40 DAY),
     DATE_SUB(CURDATE(), INTERVAL 300 DAY), 'Ninguno', 'Embarazo en curso. Sin complicaciones previas.'),
    (2, 2, 13, 19, 'Irregulares', 3, 2, 1, 1, DATE_SUB(CURDATE(), INTERVAL 20 DAY),
     DATE_SUB(CURDATE(), INTERVAL 180 DAY), 'DIU', 'Antecedente de cesarea hace 3 anios.'),
    (3, 3, 11, 20, 'Regulares / 30 dias', 1, 0, 0, 0, DATE_SUB(CURDATE(), INTERVAL 15 DAY),
     NULL, 'Anticonceptivos orales', 'Paciente primigesta.');

-- ---------------------------------------------------------------------------
-- citas
-- ---------------------------------------------------------------------------
INSERT INTO `citas`
    (`id_cita`, `id_paciente`, `id_medico`, `fecha_cita`, `hora_cita`, `estado`, `motivo_consulta`)
VALUES
    (1, 1, 1, CURDATE(),                             '08:00:00', 'Pendiente', 'Control prenatal de rutina'),
    (2, 2, 1, CURDATE(),                             '08:30:00', 'Pendiente', 'Control ginecologico anual'),
    (3, 3, 2, CURDATE(),                             '09:00:00', 'Pendiente', 'Evaluacion pediatrica del recien nacido'),
    (4, 1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY),   '08:00:00', 'Atendida',  'Primera consulta prenatal'),
    (5, 2, 2, DATE_SUB(CURDATE(), INTERVAL 14 DAY),  '09:30:00', 'Atendida',  'Control post-operatorio'),
    (6, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY),   '10:00:00', 'Pendiente', 'Ecografia morfologica'),
    (7, 3, 1, DATE_ADD(CURDATE(), INTERVAL 3 DAY),   '11:00:00', 'Pendiente', 'Consulta de planificacion familiar'),
    (8, 2, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY),  '08:30:00', 'Cancelada', 'Consulta cancelada por la paciente'),
    (9, 1, 1, DATE_SUB(CURDATE(), INTERVAL 35 DAY),  '08:00:00', 'Atendida',  'Apertura de historia clinica');

-- ---------------------------------------------------------------------------
-- detalle_historia (consultas realizadas)
-- ---------------------------------------------------------------------------
INSERT INTO `detalle_historia`
    (`id_consulta`, `id_cita`, `id_paciente`, `id_medico`, `tipo_consulta`,
     `enfermedad_actual`, `examen_fisico`, `diagnostico`, `tratamiento_plan`,
     `notas_evolucion`, `fecha_consulta`)
VALUES
    (1, 4, 1, 1, 'Primera Vez / Apertura',
     'Paciente refiere amenorrea de 8 semanas, nauseas matutinas y astenia.',
     'Abdomen blando, depresible. Mucosas humedas. TA 110/70, FC 78 lpm, Temp 36.5 C, Peso 62 kg.',
     'Embarazo intrauterino de 8 semanas por FUM.',
     'Acido folico 5mg VO diario. Hierro 1 tableta/dia. Control en 4 semanas. Solicitar laboratorio prenatal.',
     'Buena evolucion. Se indica hidratacion y dieta fraccionada para las nauseas.',
     DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
    (2, 5, 2, 2, 'Control / Evolucion',
     'Paciente acude a control post-operatorio, refiere dolor leve en herida quirurgica.',
     'Herida quirurgica limpia, sin signos de infeccion. TA 120/80, FC 82 lpm, Temp 36.8 C.',
     'Post-operatorio de cesarea en adecuada evolucion.',
     'Analgesico condicional. Curacion cada 48 horas. Reposo relativo por 2 semanas.',
     'Se retiran puntos en consulta. Herida en buen estado.',
     DATE_SUB(CURDATE(), INTERVAL 14 DAY)),
    (3, 9, 1, 1, 'Primera Vez / Apertura',
     'Paciente acude por primera vez para apertura de historia clinica.',
     'Paciente en buen estado general. TA 108/68, FC 74 lpm, Temp 36.4 C, Peso 60 kg, Talla 1.62 m.',
     'Paciente sana, apertura de historia obstetrica.',
     'Se solicitan examenes de rutina. Se agenda proximo control.',
     'Se entrega carnet de control prenatal.',
     DATE_SUB(CURDATE(), INTERVAL 35 DAY));

-- ---------------------------------------------------------------------------
-- adjuntos_archivos
-- ---------------------------------------------------------------------------
INSERT INTO `adjuntos_archivos`
    (`id_adjunto`, `id_consulta`, `nombre_archivo`, `ruta_archivo`, `tipo_archivo`, `descripcion`)
VALUES
    (1, 1, 'laboratorio-prenatal.pdf', '/uploads/consulta-1/laboratorio-prenatal.pdf', 'application/pdf',
     'Resultados de laboratorio del primer trimestre'),
    (2, 1, 'ecografia-8-semanas.jpg', '/uploads/consulta-1/ecografia-8-semanas.jpg', 'image/jpeg',
     'Ecografia de control 8 semanas'),
    (3, 2, 'informe-operatorio.pdf', '/uploads/consulta-2/informe-operatorio.pdf', 'application/pdf',
     'Informe de cesarea previa');

-- ---------------------------------------------------------------------------
-- horarios_medico (extension del proyecto)
-- ---------------------------------------------------------------------------
INSERT INTO `horarios_medico`
    (`id_horario`, `id_medico`, `dia_semana`, `hora_inicio`, `hora_fin`, `cupos_max`, `consultorio`, `activo`)
VALUES
    (1, 1, 'Lunes',     '07:00:00', '12:00:00', 10, 'Consultorio 1', 1),
    (2, 1, 'Martes',    '07:00:00', '12:00:00', 10, 'Consultorio 1', 1),
    (3, 1, 'Miercoles', '07:00:00', '12:00:00', 8,  'Consultorio 1', 1),
    (4, 1, 'Jueves',    '07:00:00', '12:00:00', 10, 'Consultorio 1', 1),
    (5, 1, 'Viernes',   '07:00:00', '12:00:00', 10, 'Consultorio 1', 1),
    (6, 2, 'Lunes',     '08:00:00', '12:00:00', 8,  'Consultorio 3', 1),
    (7, 2, 'Miercoles', '08:00:00', '12:00:00', 8,  'Consultorio 3', 1),
    (8, 2, 'Viernes',   '08:00:00', '12:00:00', 6,  'Consultorio 3', 1);
