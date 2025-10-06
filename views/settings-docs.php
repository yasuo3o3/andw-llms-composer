<?php
/**
 * Static documentation tab based on README.md snapshot.
 *
 * @package andw-llms-composer
 */

?>
<div class="wrap andw-llms-docs">
	<h2>andW LLMS Composer について</h2>
	<p>Markdown ドキュメントを軸に WordPress サイトの要約 (llms.txt) と sitemap.xml を自動生成し、公開 HTML との整合を維持するためのプラグインです。<code>readme.txt</code> に掲載している要点を運用者向けにまとめています。</p>

	<h3>特長</h3>
	<ul>
		<li>公開 HTML を真実源にした同期: 投稿・固定ページの更新を監視し、Markdown と HTML を双方向で同期。ページ単位で「HTML 優先 / Markdown 優先 / 自動」を切り替え可能。</li>
		<li>llms.txt 自動生成とガード: サイトマップや手動登録リンクをスコアリングし、HTTP ステータス検証・リダイレクト正規化・行数/文字数ガードを通過した要約を生成。既定で 15 分間キャッシュ。</li>
		<li>sitemap.xml 連携: 既存の sitemap.xml / wp-sitemap.xml を取り込み、未整備の場合はプラグインが <code>/wp-content/andw-llms-composer/</code> 配下に生成。</li>
		<li>運用 UI: 管理画面メニュー「LLMS Composer」に概要・リンク・同期・出力・ドキュメントの各タブを用意し、要約文編集や同期ジョブ実行、検証ログ確認をブラウザで完結。</li>
		<li>REST / WP-CLI 対応: <code>andw-llms-composer/v1</code> REST API と <code>wp andwllms regen</code> コマンドで自動化連携が可能。</li>
	</ul>

	<p><strong>ロードマップ:</strong> 今後のリリースで多言語・マルチサイト対応などを予定しています。</p>

	<h3>動作要件</h3>
	<ul>
		<li>WordPress 6.0 以上</li>
		<li>PHP 7.4 以上</li>
		<li><code>wp i18n make-pot</code> コマンドが利用できる環境 (翻訳テンプレート生成に使用)</li>
	</ul>

	<h3>ディレクトリ構成</h3>
	<pre><code>andw-llms-composer/
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
</code></pre>

	<h3>インストールと初期セットアップ</h3>
	<ol>
		<li>プラグイン ZIP を <code>/wp-content/plugins/</code> にアップロードし、有効化します。</li>
		<li>有効化時に <code>wp-content/andw-llms-composer/content-md/</code> が作成され、サンプル Markdown がコピーされます。</li>
		<li>管理画面「LLMS Composer」→「概要」タブでサイト概要・要約・補足メモを登録します。</li>
		<li>「リンク集」タブで主要リンク (title / url / summary / priority / locale) を編集します。空行は自動的に無視されます。</li>
		<li>「同期」タブで Markdown と HTML の同期方向ロックや同期ジョブの手動実行を行えます。</li>
		<li>「出力」タブで llms.txt プレビューや検証ログ、キャッシュ削除・サイトマップ再生成ボタンを確認します。</li>
	</ol>

	<h3>llms.txt の挙動</h3>
	<ul>
		<li>配信 URL: <code>https://{site}/llms.txt</code></li>
		<li>キャッシュ: 既定 15 分 (出力タブで変更または無効化可能)</li>
		<li>検証: HTTP 200 / 301 の URL を採用し、リダイレクトは正規 URL に正規化。超過分はスコア順で切り詰め。</li>
		<li>メタタグ: 任意で <code>&lt;link rel="alternate" type="text/markdown"&gt;</code> と <code>&lt;script type="text/llms.txt"&gt;</code> を自動挿入。</li>
	</ul>

	<h3>自動化インターフェース</h3>
	<h4>REST API</h4>
	<ul>
		<li><code>GET /wp-json/andw-llms-composer/v1/preview</code>: 最新の llms.txt 内容を取得。</li>
		<li><code>POST /wp-json/andw-llms-composer/v1/regenerate?target=llms|sitemap|all</code>: キャッシュやサイトマップを再生成。</li>
	</ul>
	<p>いずれも <code>manage_options</code> 権限と nonce 検証が必要です。</p>

	<h4>WP-CLI</h4>
	<pre><code>wp andwllms regen --target=llms     # llms.txt キャッシュをクリア
wp andwllms regen --target=sitemap  # サイトマップを再生成
wp andwllms regen --target=all      # llms.txt とサイトマップの両方
</code></pre>

	<h3>セキュリティと実装ポリシー</h3>
	<ul>
		<li>すべての管理操作は <code>manage_options</code> 権限と nonce 検証を通過します。</li>
		<li>フォーム入力は <code>sanitize_text_field()</code> / <code>sanitize_textarea_field()</code> / <code>esc_url_raw()</code> で洗浄。</li>
		<li>出力は <code>esc_html()</code> / <code>esc_attr()</code> / <code>esc_url()</code> でエスケープ。</li>
	</ul>

	<h3>開発メモ</h3>
	<ul>
		<li><code>bin/make-pot.sh</code> で <code>languages/andw-llms-composer.pot</code> を更新できます (wp-cli i18n コマンドが必要)。</li>
		<li><code>docs/</code> 以下には開発規約や会話ログを保管しており、配布 ZIP には含めません。</li>
		<li>WordPress Coding Standards に準拠し、コミット時は <code>Version</code> / <code>Stable tag</code> を 0.0.1 起点で 0.0.1 刻みで更新します。</li>
	</ul>

	<h3>ロードマップ</h3>
	<ul>
		<li>多言語・マルチサイト対応</li>
		<li>Markdown 逆変換対象要素の拡張</li>
		<li>重要度スコア算出アルゴリズムの外部設定化</li>
	</ul>

	<h3>ライセンス</h3>
	<p>GPLv2 以上。詳細は <code>LICENSE</code> を参照してください。</p>

	<p class="description">※このタブは README.md をベースに静的生成した内容です。更新が必要な場合は README.md の修正後に本ファイルを再生成してください。</p>
</div>
