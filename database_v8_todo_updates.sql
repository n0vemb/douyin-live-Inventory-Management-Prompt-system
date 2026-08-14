-- 待办事项更新记录表 v8
CREATE TABLE IF NOT EXISTS `todo_updates` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `todo_id`     INT NOT NULL,
  `content`     TEXT NOT NULL,
  `assignees`   JSON NULL,
  `updated_by`  INT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_todo_created` (`todo_id`, `created_at`),
  CONSTRAINT `fk_todo_upd_todo` FOREIGN KEY (`todo_id`) REFERENCES `todo_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
