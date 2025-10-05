=== andW LLMS Composer ===
Contributors: yasuo3o3
Tags: markdown, sitemap, llms
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
andW LLMS Composer は、公開 HTML を真実源としつつ Markdown ドキュメントを安全に同期させ、サイトの要約となる llms.txt と sitemap.xml を自動生成するためのツールです。コンテンツ編集者は Markdown ベースで管理しつつ、既存ページの更新を取りこぼさずに整合を保てます。

主な機能:
* Markdown と公開 HTML の双方向同期（ページ単位で優先方向ロック可能）
* llms.txt の自動生成と検証（HTTP ステータス確認、リダイレクト正規化、キャッシュ、ガード）
* 既存サイトマップの取り込み、または優先度スコアベースの自前生成
* 管理画面での概要設定、重要リンク編集、同期ジョブ管理、プレビュー
* REST API と WP-CLI からの再生成操作

> 次段階で多言語・マルチサイト対応を提供予定です。

== Installation ==
1. プラグイン ZIP を `/wp-content/plugins/` にアップロードし、有効化します。
2. 有効化時に `wp-content/andw-llms-composer/content-md/` が自動的に作成され、サンプル Markdown が配置されます。
3. 管理画面「LLMS Composer」メニューから概要やリンク、同期設定を構成してください。
4. 「出力」タブで llms.txt のプレビューと検証ログを確認できます。

== Frequently Asked Questions ==

= llms.txt はどこで確認できますか？ =
https://example.com/llms.txt のようにサイトルート直下で配信されます。キャッシュは既定で 15 分です。

= Markdown より HTML を優先したい場合は？ =
同期タブから対象ページの「HTML priority」を選択してください。逆に Markdown を優先させる場合は「Markdown priority」を選択します。

= sitemap.xml を既存プラグインと併用できますか？ =
はい。既存の sitemap.xml / wp-sitemap.xml が存在する場合は自動的に取り込みます。無い場合はプラグインが独自の sitemap.xml を `wp-content/andw-llms-composer/` に生成します。

== Screenshots ==
1. 管理画面の概要タブ
2. 重要リンク編集テーブル
3. 同期タブのドキュメント一覧
4. 出力タブの llms.txt プレビュー

== Changelog ==
= 0.0.1 =
* 初回リリース

== Upgrade Notice ==
= 0.0.1 =
初回リリース版です。

== Translators ==
* `bin/make-pot.sh` を使用して `.pot` を再生成できます。
