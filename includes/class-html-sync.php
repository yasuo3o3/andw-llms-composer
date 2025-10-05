<?php
/**
 * HTML/Markdown synchronization logic.
 *
 * @package andw-llms-composer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage bi-directional synchronization between markdown and posts.
 */
class Andw_LLMS_Composer_Html_Sync {
	/**
	 * Markdown store.
	 *
	 * @var Andw_LLMS_Composer_Md_Store
	 */
	protected $store;

	/**
	 * Reentrancy guard for post updates.
	 *
	 * @var bool
	 */
	protected static $suspending_post_save = false;

	/**
	 * Cached lock configuration.
	 *
	 * @var array
	 */
	protected $locks = array();

	/**
	 * Constructor.
	 *
	 * @param Andw_LLMS_Composer_Md_Store $store Markdown store.
	 */
	public function __construct( Andw_LLMS_Composer_Md_Store $store ) {
		$this->store = $store;
	}

	/**
	 * Initialize service.
	 *
	 * @return void
	 */
	public function init() {
		$this->locks = get_option( 'andw_llms_composer_locks', array() );
	}

	/**
	 * Capture post HTML and update markdown or reverse when applicable.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function capture_post_html( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'revision' === $post->post_type ) {
			return;
		}

		$direction = $this->get_lock( $post_id );
		$doc_id    = $this->document_id_for_post( $post );
		$document  = $this->store->get( $doc_id );

		$post_modified = mysql2date( 'U', $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified, false );
		$doc_updated   = $document ? mysql2date( 'U', $document['meta']['updated_at'], false ) : 0;

		if ( ! $document || 'html' === $direction || $post_modified >= $doc_updated ) {
			$this->sync_html_to_markdown( $post, $document );
			return;
		}

		$this->sync_markdown_to_html( $post, $document );
	}

	/**
	 * Mirror post HTML into markdown document.
	 *
	 * @param WP_Post    $post Post object.
	 * @param array|null $existing Existing document.
	 * @return void
	 */
	protected function sync_html_to_markdown( $post, $existing ) {
		$body = $this->html_to_markdown( $post->post_content );

		$meta = $existing ? $existing['meta'] : array();

		$meta['id']             = $this->document_id_for_post( $post );
		$meta['canonical_url']  = get_permalink( $post );
		$meta['slug']           = $post->post_name;
		$meta['locale']         = get_locale();
		$meta['title']          = get_the_title( $post );
		$meta['summary']        = $existing ? $existing['meta']['summary'] : wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 40 );
		$meta['html_synced_at'] = current_time( 'mysql', true );
		$meta['updated_at']     = current_time( 'mysql', true );

		$this->store->save( $meta, $body );
	}

	/**
	 * Apply markdown content to WordPress post.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $document Document data.
	 * @return void
	 */
	protected function sync_markdown_to_html( $post, $document ) {
		if ( self::$suspending_post_save ) {
			return;
		}

		$new_html = $this->markdown_to_html( $document['body'] );

		$payload = array(
			'ID'           => $post->ID,
			'post_content' => $new_html,
		);

		self::$suspending_post_save = true;
		wp_update_post( $payload );
		self::$suspending_post_save = false;

		$document['meta']['html_synced_at'] = current_time( 'mysql', true );
		$this->store->save( $document['meta'], $document['body'] );
	}

	/**
	 * Get lock direction for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_lock( $post_id ) {
		$lock  = isset( $this->locks[ $post_id ] ) ? $this->locks[ $post_id ] : andw_llms_composer_get_setting( 'default_lock', 'html' );
		$valid = array( 'html', 'markdown', 'auto' );

		return in_array( $lock, $valid, true ) ? $lock : 'html';
	}

	/**
	 * Update lock direction for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lock Lock direction.
	 * @return void
	 */
	public function set_lock( $post_id, $lock ) {
		$valid = array( 'html', 'markdown', 'auto' );

		if ( ! in_array( $lock, $valid, true ) ) {
			return;
		}

		$this->locks[ $post_id ] = $lock;
		update_option( 'andw_llms_composer_locks', $this->locks );
	}

	/**
	 * Convert HTML to markdown text.
	 *
	 * @param string $html HTML string.
	 * @return string
	 */
	public function html_to_markdown( $html ) {
		$html = (string) $html;

		if ( '' === trim( $html ) ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$markdown = $this->render_node_list_markdown( $dom->childNodes );
		$markdown = preg_replace( "#\n{3,}#", "\n\n", trim( $markdown ) );

		return $markdown;
	}

	/**
	 * Recursively render markdown from node list.
	 *
	 * @param DOMNodeList $nodes Node list.
	 * @param int         $depth Nesting depth.
	 * @return string
	 */
	protected function render_node_list_markdown( $nodes, $depth = 0 ) {
		$output = '';

		foreach ( $nodes as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType ) {
				$text   = trim( preg_replace( '#\s+#', ' ', $node->nodeValue ) );
				$output .= $text;
				continue;
			}

			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}

			switch ( $node->nodeName ) {
				case 'p':
					$output .= "\n\n" . $this->render_node_list_markdown( $node->childNodes, $depth );
					break;
				case 'br':
					$output .= "  \n";
					break;
				case 'h1':
				case 'h2':
				case 'h3':
				case 'h4':
				case 'h5':
				case 'h6':
					$level   = (int) substr( $node->nodeName, 1 );
					$output .= "\n\n" . str_repeat( '#', $level ) . ' ' . trim( $this->render_node_list_markdown( $node->childNodes, $depth ) );
					break;
				case 'strong':
				case 'b':
					$output .= '**' . $this->render_node_list_markdown( $node->childNodes, $depth ) . '**';
					break;
				case 'em':
				case 'i':
					$output .= '*' . $this->render_node_list_markdown( $node->childNodes, $depth ) . '*';
					break;
				case 'code':
					$output .= '`' . trim( $node->textContent ) . '`';
					break;
				case 'pre':
					$output .= "\n\n```\n" . trim( $node->textContent ) . "\n```";
					break;
				case 'ul':
				case 'ol':
					$output .= "\n" . $this->render_list( $node, $depth );
					break;
				case 'li':
					$prefix = $this->is_ordered_list( $node->parentNode ) ? ( $this->list_index( $node ) . '. ' ) : '- ';
					$output .= str_repeat( '  ', $depth ) . $prefix . trim( $this->render_node_list_markdown( $node->childNodes, $depth + 1 ) ) . "\n";
					break;
				case 'a':
					$href   = $node->getAttribute( 'href' );
					$text   = trim( $this->render_node_list_markdown( $node->childNodes, $depth ) );
					$output .= '[' . $text . '](' . esc_url_raw( $href ) . ')';
					break;
				case 'img':
					$alt = $node->getAttribute( 'alt' );
					$src = $node->getAttribute( 'src' );
					$output .= '![' . sanitize_text_field( $alt ) . '](' . esc_url_raw( $src ) . ')';
					break;
				case 'blockquote':
					$quote = trim( $this->render_node_list_markdown( $node->childNodes, $depth ) );
					$lines = array_map( function ( $line ) {
						return '> ' . $line;
					}, preg_split( "#\n#", $quote ) );
					$output .= "\n\n" . implode( "\n", $lines );
					break;
				default:
					$output .= $this->render_node_list_markdown( $node->childNodes, $depth );
					break;
			}
		}

		return $output;
	}

	/**
	 * Render list container contents.
	 *
	 * @param DOMNode $node Node.
	 * @param int     $depth Depth.
	 * @return string
	 */
	protected function render_list( $node, $depth ) {
		return $this->render_node_list_markdown( $node->childNodes, $depth + 1 );
	}

	/**
	 * Check if list ordered.
	 *
	 * @param DOMNode $node Node.
	 * @return bool
	 */
	protected function is_ordered_list( $node ) {
		return $node && 'ol' === strtolower( $node->nodeName );
	}

	/**
	 * Determine ordered list index.
	 *
	 * @param DOMNode $node Node.
	 * @return int
	 */
	protected function list_index( $node ) {
		$index = 1;
		$prev  = $node->previousSibling;

		while ( $prev ) {
			if ( XML_ELEMENT_NODE === $prev->nodeType && 'li' === $prev->nodeName ) {
				$index++;
			}

			$prev = $prev->previousSibling;
		}

		return $index;
	}

	/**
	 * Convert markdown to HTML.
	 *
	 * @param string $markdown Markdown text.
	 * @return string
	 */
	public function markdown_to_html( $markdown ) {
		$markdown = trim( (string) $markdown );

		if ( '' === $markdown ) {
			return '';
		}

		$lines      = preg_split( "#\n#", $markdown );
		$html       = '';
		$in_code    = false;
		$list_stack = array();

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				while ( ! empty( $list_stack ) && ! $this->is_list_continuation( $lines, $line ) ) {
					$html .= $this->close_list( $list_stack );
				}
				$html .= "\n";
				continue;
			}

			if ( 0 === strpos( $line, '```' ) ) {
				if ( $in_code ) {
					$html .= '</code></pre>';
				} else {
					$html .= '<pre><code>';
				}
				$in_code = ! $in_code;
				continue;
			}

			if ( $in_code ) {
				$html .= esc_html( $line ) . "\n";
				continue;
			}

			if ( preg_match( '/^(#{1,6})\s+(.*)$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$html .= '<h' . $level . '>' . $this->render_inline_markdown( $m[2] ) . '</h' . $level . '>';
				continue;
			}

			if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
				$html .= '<blockquote><p>' . $this->render_inline_markdown( $m[1] ) . '</p></blockquote>';
				continue;
			}

			if ( preg_match( '/^\-\s+(.*)$/', $line, $m ) ) {
				$html .= $this->open_list( 'ul', $list_stack ) . '<li>' . $this->render_inline_markdown( $m[1] ) . '</li>';
				continue;
			}

			if ( preg_match( '/^\d+\.\s+(.*)$/', $line, $m ) ) {
				$html .= $this->open_list( 'ol', $list_stack ) . '<li>' . $this->render_inline_markdown( $m[1] ) . '</li>';
				continue;
			}

			while ( ! empty( $list_stack ) ) {
				$html .= $this->close_list( $list_stack );
			}

			$html .= '<p>' . $this->render_inline_markdown( $line ) . '</p>';
		}

		while ( ! empty( $list_stack ) ) {
			$html .= $this->close_list( $list_stack );
		}

		if ( $in_code ) {
			$html .= '</code></pre>';
		}

		return trim( $html );
	}

	/**
	 * Helper to detect list continuation (noop placeholder).
	 *
	 * @param array  $lines Lines.
	 * @param string $line Current line.
	 * @return bool
	 */
	protected function is_list_continuation( $lines, $line ) {
		return false;
	}

	/**
	 * Open list container if needed.
	 *
	 * @param string $type List type.
	 * @param array  &$stack Stack reference.
	 * @return string
	 */
	protected function open_list( $type, &$stack ) {
		$output = '';
		if ( empty( $stack ) || end( $stack ) !== $type ) {
			$output .= '<' . $type . '>';
			$stack[] = $type;
		}

		return $output;
	}

	/**
	 * Close the last list.
	 *
	 * @param array &$stack Stack.
	 * @return string
	 */
	protected function close_list( &$stack ) {
		$type = array_pop( $stack );
		return '</' . $type . '>';
	}

	/**
	 * Render inline markdown to HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function render_inline_markdown( $text ) {
		$text = preg_replace_callback( '/`([^`]+)`/', function ( $matches ) {
			return '<code>' . esc_html( $matches[1] ) . '</code>';
		}, $text );

		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
		$text = preg_replace_callback( '/!\[(.*?)\]\((.*?)\)/', function ( $matches ) {
			return '<img src="' . esc_url( $matches[2] ) . '" alt="' . esc_attr( $matches[1] ) . '" />';
		}, $text );
		$text = preg_replace_callback( '/\[(.*?)\]\((.*?)\)/', function ( $matches ) {
			return '<a href="' . esc_url( $matches[2] ) . '">' . esc_html( $matches[1] ) . '</a>';
		}, $text );

		return $text;
	}

	/**
	 * Map post object to document id.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	protected function document_id_for_post( $post ) {
		return $post->post_type . '-' . $post->ID;
	}
}
