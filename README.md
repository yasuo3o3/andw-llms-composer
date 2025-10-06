# andW LLMS Composer

Markdown ドキュメントを軸に WordPress サイトの要約 (llms.txt) と sitemap.xml を自動生成し、公開 HTML との整合を維持するためのプラグインです。readme.txt に掲載している WordPress.org 向け情報を、開発者と運用担当者が把握しやすい形でまとめています。

## 特長
- 公開 HTML を真実源にした同期: 投稿・固定ページの更新を監視し、Markdown と HTML を双方向で同期します。ページ単位で HTML 優先 / Markdown 優先 / 自動を切り替え可能。
- llms.txt 自動生成とガード: サイトマップや手動登録リンクをスコアリングし、HTTP ステータス検証やリダイレクト正規化、行数・文字数ガードを通過した要約を生成。既定で 15 分間キャッシュします。
- sitemap.xml 連携: 既存の sitemap.xml / wp-sitemap.xml を取り込み、未整備の場合はプラグインが /wp-content/andw-llms-composer/ 配下に生成します。
- 運用オペレーションを支援する UI: 管理画面メニュー「LLMS Composer」に概要・リンク・同期・出力の 4 タブを提供。要約文や主要リンクの編集、同期ジョブの実行、llms.txt プレビューや検証ログ確認をブラウザで一元管理できます。
- REST / WP-CLI 対応: andw-llms-composer/v1 REST API と wp andwllms regen コマンドを備え、CI や運用スクリプトから再生成をトリガーできます。

> 今後のリリースで多言語・マルチサイト対応を追加予定です。

## 動作要件
- WordPress 6.0 以上
- PHP 7.4 以上
- wp i18n make-pot コマンドが利用できる環境 (bin/make-pot.sh で翻訳テンプレートを再生成)

## ディレクトリ構成
    andw-llms-composer/
    ├── andw-llms-composer.php      # エントリーポイント
    ├── includes/
    │   ├── class-plugin.php        # サービスローダー
    │   ├── class-admin.php         # 管理画面タブ
    │   ├── class-md-store.php      # Markdown ストレージ
    │   ├── class-html-sync.php     # HTML ↔ Markdown 同期
    │   ├── class-llms-builder.php  # llms.txt 生成・検証
    │   ├── class-sitemap.php       # サイトマップ取り込み/生成
    │   ├── class-rest.php          # REST API エンドポイント
    │   ├── class-cli.php           # WP-CLI コマンド
    │   ├── class-i18n.php          # 日本語ローカライズ補助
    │   └── helpers.php             # 共通ユーティリティ
    ├── views/                      # 管理画面テンプレート
    ├── content-md/                 # 同期対象 Markdown (非公開)
    ├── languages/andw-llms-composer.pot
    ├── assets/admin.css
    ├── bin/make-pot.sh             # 翻訳テンプレート生成スクリプト
    └── readme.txt                  # WordPress.org 用メタ情報

## インストールと初期セットアップ
1. プラグイン ZIP を /wp-content/plugins/ にアップロードし、有効化します。
2. 有効化時に wp-content/andw-llms-composer/content-md/ が作成され、サンプル Markdown がコピーされます。
3. 管理画面「LLMS Composer」→「概要」タブでサイト概要・要約・補足メモを登録します。
4. 「リンク集」タブで主要リンク (title / url / summary / priority / locale) を編集します。空行は自動的に無視されます。
5. 「同期」タブから Markdown と HTML の同期方向ロックや同期ジョブの手動実行を行えます。
6. 「出力」タブで llms.txt プレビューや検証ログ、キャッシュ削除・サイトマップ再生成ボタンを確認します。

## llms.txt の挙動
- 配信 URL: https://{site}/llms.txt
- キャッシュ: 既定 15 分 (出力タブで変更または無効化)
- 検証: 200 または 301 の URL のみ採用し、リダイレクトは正規 URL に正規化。超過分はスコア順で切り詰め。
- メタタグ: 任意で link rel="alternate" type="text/markdown" と script type="text/llms.txt" を自動挿入。

## 自動化インターフェース
### REST API
- GET /wp-json/andw-llms-composer/v1/preview: 最新の llms.txt 内容を取得。
- POST /wp-json/andw-llms-composer/v1/regenerate?target=llms|sitemap|all: キャッシュやサイトマップの再生成。

いずれも manage_options 権限と nonce 検証が必要です。

### WP-CLI
    wp andwllms regen --target=llms     # llms.txt キャッシュをクリア
    wp andwllms regen --target=sitemap  # サイトマップを再生成
    wp andwllms regen --target=all      # llms.txt とサイトマップの両方

## セキュリティと実装ポリシー
- すべての管理操作は manage_options 権限と nonce 検証を通過します。
- フォーム入力は sanitize_text_field / sanitize_textarea_field / esc_url_raw などで洗浄。
- 出力は esc_html / esc_attr / esc_url を通してエスケープしています。

## 開発メモ
- bin/make-pot.sh で languages/andw-llms-composer.pot を更新できます (wp-cli i18n コマンドが必要)。
- docs/ 以下には開発規約や会話ログを保管しており、配布 ZIP には含めません。
- WordPress Coding Standards に準拠し、コミット時は Version / Stable tag を 0.0.1 起点で 0.0.1 刻みで更新します。

## ロードマップ
- 多言語・マルチサイト対応
- Markdown 逆変換対象要素の拡張
- 重要度スコア算出アルゴリズムの外部設定化

## ライセンス
GPLv2 以上。詳細は LICENSE を参照してください。
