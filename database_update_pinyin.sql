-- 为 products 表添加拼音首字母列
ALTER TABLE products ADD COLUMN pinyin_initials VARCHAR(100) DEFAULT NULL COMMENT '商品名称拼音首字母，用于快速检索' AFTER name;
CREATE INDEX idx_pinyin_initials ON products(pinyin_initials);
