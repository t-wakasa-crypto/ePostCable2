---
name: db-design-standard
description: DB設計の命名規則・正規化基準。db-designerエージェントが
             テーブル設計をするときに自動適用される。
allowed-tools: Read
effort: medium
---

# DB設計 品質基準

## 命名規則
詳細は `references/naming-conventions.md` を参照すること。

基本ルール：
- テーブル名：スネークケース複数形（users, order_items）
- 主キー：id（BIGINT UNSIGNED AUTO_INCREMENT）
- 外部キー：参照テーブル名の単数形_id（user_id, product_id）
- 全テーブルに created_at, updated_at を付ける（論理削除の場合は deleted_at も）

## 正規化方針
正規化ルールの詳細は `references/normalization-rules.md` を参照すること。

- 原則：第3正規形
- パフォーマンス上の理由で非正規化する場合はカラムコメントに理由を記載

## アウトプット形式
以下をすべて含めること：
1. ER図（Mermaid erDiagram形式）
2. 各テーブルの CREATE TABLE 文（コメント付き）
3. インデックス設計と選定理由
4. 懸念事項・検討事項
