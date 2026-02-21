-- PlugPlay Shop - Script de criação do banco (MySQL/MariaDB)
-- Uso sugerido:
-- 1) Ajuste o nome do banco em DB_NAME, se desejar
-- 2) Execute este script no seu servidor MySQL/MariaDB
-- 3) Configure o arquivo .env da aplicação e suba o site

-- Cria o banco de dados
CREATE DATABASE IF NOT EXISTS `plugplayshop`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `plugplayshop`;

-- Tabela de produtos
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `price` DECIMAL(10,2) DEFAULT 0,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
  `category` VARCHAR(100),
  `image_urls` TEXT,
  `affiliate_url` VARCHAR(500),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices auxiliares em products
CREATE INDEX `idx_category` ON `products` (`category`);
CREATE INDEX `idx_name` ON `products` (`name`);

-- Tabela de cliques em links de afiliado
CREATE TABLE IF NOT EXISTS `clicks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `source` VARCHAR(20),
  `ip` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `referer` VARCHAR(500),
  `utm_source` VARCHAR(100),
  `utm_medium` VARCHAR(100),
  `utm_campaign` VARCHAR(100),
  `landing_path` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_clicks_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices auxiliares em clicks
CREATE INDEX `idx_clicks_product` ON `clicks` (`product_id`);
CREATE INDEX `idx_clicks_created` ON `clicks` (`created_at`);

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de tentativas de login
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100),
  `ip` VARCHAR(45),
  `attempt_count` INT DEFAULT 0,
  `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `locked_until` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_login_user_ip` (`username`, `ip`),
  INDEX `idx_login_locked` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEEDS OPCIONAIS
-- Observação: para criar o usuário admin diretamente via SQL, é necessário
-- informar um hash bcrypt da senha. Se preferir, suba a aplicação e o
-- ensure_schema() fará o seed automático do usuário 'admin' com senha 'admin123'.
-- Exemplo (substitua <HASH_BCRYPT_DO_ADMIN123> por um hash válido gerado em PHP):
-- INSERT INTO `users` (`username`, `password_hash`) VALUES ('admin', '<HASH_BCRYPT_DO_ADMIN123>');

-- Produtos de exemplo para facilitar testes (opcional)
INSERT INTO `products` (`name`, `description`, `price`, `currency`, `category`, `image_urls`, `affiliate_url`) VALUES
('Fone Bluetooth Pro', 'Som nítido, cancelamento de ruído e bateria de longa duração.', 349.90, 'USD', 'Eletrônicos', 'https://images.unsplash.com/photo-1518444027693-0f5f39de3f4c?q=80&w=1200&auto=format&fit=crop,https://images.unsplash.com/photo-1511367461989-f85a21fda167?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/fone-pro'),
('Smartwatch Active', 'Monitore saúde, notificações e treinos com estilo.', 599.00, 'USD', 'Fitness', 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=1200&auto=format&fit=crop,https://images.unsplash.com/photo-1541532713592-79a0317b6b77?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/smartwatch-active'),
('Mouse Gamer X', 'Alta precisão, RGB e ergonomia para longas sessões.', 249.00, 'USD', 'Eletrônicos', 'https://images.unsplash.com/photo-1544829097-3eac1049f3c3?q=80&w=1200&auto=format&fit=crop,https://images.unsplash.com/photo-1517336714731-489689fd1ca8?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/mouse-gamer-x'),
('Caixa de Som Portátil', 'Graves potentes e resistência à água para qualquer momento.', 399.99, 'USD', 'Eletrônicos', 'https://images.unsplash.com/photo-1557682224-5b8590b01584?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/caixa-portatil'),
('Cafeteira Compacta', 'Seu café perfeito em minutos, design minimalista.', 329.90, 'USD', 'Casa', 'https://images.unsplash.com/photo-1502945015378-0e284ca06ccb?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/cafeteira-compacta'),
('Teclado Mecânico TKL', 'Feedback tátil, switches silenciosos e construção robusta.', 459.00, 'USD', 'Eletrônicos', 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?q=80&w=1200&auto=format&fit=crop', 'https://example.com/affiliado/teclado-tkl');