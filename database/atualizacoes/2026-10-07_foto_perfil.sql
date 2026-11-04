-- Foto de perfil (arquivo salvo em uploads/avatars/)
USE reserva_study;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto VARCHAR(100) NULL AFTER curso_desejado;
