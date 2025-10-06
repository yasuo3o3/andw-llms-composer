<?php
/**
 * Localization helpers.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide minimal inline translations when Japanese is active.
 */
class Andw_LLMS_Composer_I18n {
	/**
	 * Translation map keyed by original string.
	 *
	 * @var array<string, string>
	 */
	protected $translations = array(
		'Actions'                               => 'アクション',
		'All'                                   => 'すべて',
		'Auto'                                  => '自動',
		'Cache TTL (minutes)'                   => 'キャッシュ保持時間（分）',
		'Cache cleared.'                        => 'キャッシュを削除しました。',
		'Clear cache'                           => 'キャッシュを削除',
		'Docs'                                  => 'ドキュメント',
		'Direction'                             => '同期方向',
		'Document ID is required.'              => 'ドキュメントIDは必須です。',
		'Documents'                             => 'ドキュメント',
		'Embed llms script'                     => 'llmsスクリプトを埋め込む',
		'Enable cache'                          => 'キャッシュを有効化',
		'Execute sync'                          => '同期を実行',
		'Failed to fetch %s'                    => '%s の取得に失敗しました',
		'Failed to write markdown file.'        => 'Markdownファイルの書き込みに失敗しました。',
		'Generate sitemap.xml'                  => 'sitemap.xmlを生成',
		'HTML priority'                         => 'HTML優先',
		'Inject alternate link'                 => 'alternateリンクを挿入',
		'LLMS Composer'                         => 'LLMSコンポーザー',
		'Links'                                 => 'リンク',
		'Links updated.'                        => 'リンクを更新しました。',
		'Locale'                                => 'ロケール',
		'Markdown'                              => 'Markdown',
		'Markdown priority'                     => 'Markdown優先',
		'No markdown'                           => 'Markdownが存在しません',
		'Notes'                                 => 'メモ',
		'Output'                                => '出力',
		'Output settings saved.'                => '出力設定を保存しました。',
		'Overview'                              => '概要',
		'Overview saved.'                       => '概要を保存しました。',
		'Post'                                  => '投稿',
		'Primary Links'                         => '主要リンク',
		'Priority'                              => '優先度',
		'Rebuild sitemap'                       => 'サイトマップを再生成',
		'Run sync job'                          => '同期ジョブを実行',
		'Save links'                            => 'リンクを保存',
		'Save output settings'                  => '出力設定を保存',
		'Save overview'                         => '概要を保存',
		'Site title'                            => 'サイトタイトル',
		'Sitemap regenerated.'                  => 'サイトマップを再生成しました。',
		'Sorry, you are not allowed to access this resource.' => '権限がないため、この操作は実行できません。',
		'Summary'                               => '概要説明',
		'Sync'                                  => '同期',
		'Sync direction updated.'               => '同期方向を更新しました。',
		'Sync job executed.'                    => '同期ジョブを実行しました。',
		'Target post (optional)'                => '対象投稿（任意）',
		'Title'                                 => 'タイトル',
		'Trimmed to %d characters.'             => '%d 文字に切り詰めました。',
		'Trimmed to %d lines.'                  => '%d 行に切り詰めました。',
		'URL'                                   => 'URL',
		'Unexpected response %1$s for %2$s'     => '%2$s に対して予期しない応答 %1$s です',
		'Update'                                => '更新',
		'Updated'                               => '更新日時',
		'Validation log'                        => '検証ログ',
		'View'                                  => '表示',
		'llms.txt Preview'                     => 'llms.txt プレビュー',
	);

	/**
	 * Hook filters.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'gettext', array( $this, 'maybe_translate' ), 10, 3 );
	}

	/**
	 * Provide Japanese translation for known strings.
	 *
	 * @param string $translated Already translated string.
	 * @param string $text       Original string.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function maybe_translate( $translated, $text, $domain ) {
		if ( 'andw-llms-composer' !== $domain ) {
			return $translated;
		}

		if ( ! $this->is_japanese_locale() ) {
			return $translated;
		}

		if ( isset( $this->translations[ $text ] ) ) {
			return $this->translations[ $text ];
		}

		return $translated;
	}

	/**
	 * Determine whether the current locale represents Japanese.
	 *
	 * @return bool
	 */
	protected function is_japanese_locale() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		if ( empty( $locale ) ) {
			$locale = get_locale();
		}

		return 0 === strpos( $locale, 'ja' );
	}
}
