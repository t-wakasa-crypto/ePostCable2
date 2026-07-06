# DB命名規則 詳細

## テーブル名
- スネークケース・複数形
- 英語で意味が明確な名前
- 例：users, products, order_items, user_roles

## カラム名
- スネークケース
- ブール値：is_〇〇 または has_〇〇（例：is_active, has_subscription）
- 日時：〇〇_at（例：created_at, published_at）
- 日付：〇〇_date（例：birth_date, expiry_date）
- フラグ：避けてブール型を使う

## インデックス名
- idx_テーブル名_カラム名（例：idx_users_email）
- ユニーク：uniq_テーブル名_カラム名（例：uniq_users_email）
- 複合：idx_テーブル名_カラム1_カラム2（例：idx_orders_user_id_created_at）

## 禁止事項
- 予約語の使用（order, group, select 等）
- 略語の使用（usr, prd 等）→ 完全な単語を使う
- 型名をカラム名に含める（user_string 等）
