-- SQL para crear índices en la tabla logs

-- Índice primario en user_id
CREATE INDEX idx_logs_user_id ON logs(user_id);

-- Índice en accion para filtrar rápidamente
CREATE INDEX idx_logs_accion ON logs(accion);

-- Índice en modelo para filtrar por tipo de modelo
CREATE INDEX idx_logs_modelo ON logs(modelo);

-- Índice en modelo_id para buscar cambios de un registro específico
CREATE INDEX idx_logs_modelo_id ON logs(modelo_id);

-- Índice en created_at para ordenes y filtros por fecha
CREATE INDEX idx_logs_created_at ON logs(created_at);

-- Índice compuesto para búsquedas combinadas
CREATE INDEX idx_logs_usuario_accion_fecha ON logs(user_id, accion, created_at DESC);

-- Índice para búsquedas por IP
CREATE INDEX idx_logs_ip_address ON logs(ip_address);
