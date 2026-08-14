-- 待办事项表（handover / todo） v7
CREATE TABLE IF NOT EXISTS `todo_items` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `store_id`          INT NOT NULL,
  `content`           TEXT         NOT NULL,
  `priority`          ENUM('normal','urgent') NOT NULL DEFAULT 'normal',
  `status`            ENUM('pending','done')  NOT NULL DEFAULT 'pending',
  `creator_id`        INT NOT NULL,
  `assignees`         JSON NULL,
  `completed_by`      INT NULL,
  `completion_detail` TEXT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at`      DATETIME     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_store_status` (`store_id`, `status`),
  KEY `idx_store_created` (`store_id`, `created_at`),
  CONSTRAINT `fk_todo_store`   FOREIGN KEY (`store_id`)    REFERENCES `stores`(`id`),
  CONSTRAINT `fk_todo_creator` FOREIGN KEY (`creator_id`)  REFERENCES `users`(`id`),
  CONSTRAINT `fk_todo_doneby`  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
