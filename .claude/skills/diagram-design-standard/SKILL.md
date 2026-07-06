---
name: diagram-design-standard
description: 図解設計の品質基準。diagram-designerエージェントが
             Mermaid形式の図を作成するときに自動適用される。
allowed-tools: Read
effort: medium
---

# 図解設計 品質基準

## 使用するMermaid図の種類

| 図の種類 | Mermaidの記法 | 用途 |
|---|---|---|
| システムアーキテクチャ | `graph TD` または `C4Context` | システム全体像 |
| シーケンス図 | `sequenceDiagram` | ユースケースの処理フロー |
| ER図 | `erDiagram` | データ構造（DB設計と重複する場合は参照のみ） |
| デプロイ図 | `graph LR` | インフラ構成 |

## 品質基準
- 図のタイトルを必ず記載する
- 登場人物・コンポーネントは5〜8個以内に絞る（多すぎると読めない）
- シーケンス図は主要ユースケース3つ以上を作成する
- 各図の下に「この図の読み方」を1〜2行で補足する

## アウトプット形式
各図をコードブロック（```mermaid）で囲んで記載する。
図の前にH2見出しでタイトルを付けること。
