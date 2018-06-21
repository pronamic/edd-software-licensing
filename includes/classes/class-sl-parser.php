<?php


/**
 * Class EDD_SL_Readme_Parser
 */
class EDD_SL_Readme_Parser extends \WordPressdotorg\Plugin_Directory\Readme\Parser {

	/**
	 * @param string $text
	 *
	 * @return string
	 */
	public function parse_markdown( $text ) {
		static $markdown = null;

		if ( null === $markdown ) {
			$markdown = new Parsedown();
		}

		return $markdown->text( $text );
	}

	/**
	 * Return parsed readme.txt as array.
	 *
	 * @return array
	 */
	public function parse_data() {
		$data = array();
		foreach ( get_object_vars( $this ) as $key => $value ) {
			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * @param array $users
	 *
	 * @return array
	 */
	protected function sanitize_contributors( $users ) {
		return $users;
	}

	/**
	 * Makes generation of short description PHP 5.3 compliant.
	 * Original requires PHP 5.4 for array dereference.
	 *
	 * @return string $description[0]
	 */
	protected function short_description_53() {
		$description = array_filter( explode( "\n", $this->sections['description'] ) );

		return $description[0];
	}

	/**
	 * Converts FAQ from dictionary list to h4 style.
	 */
	protected function faq_as_h4() {
		unset( $this->sections['faq'] );
		$this->sections['faq'] = '';
		foreach ( $this->faq as $question => $answer ) {
			$this->sections['faq'] .= "<h4>{$question}</h4>\n{$answer}\n";
		}
	}

	/**
	 * Replace parent method as some users don't have `mb_strrpos()`.
	 *
	 * @access protected
	 *
	 * @param string $desc
	 * @param int    $length
	 *
	 * @return string
	 */
	protected function trim_length( $desc, $length = 150 ) {
		if ( mb_strlen( $desc ) > $length ) {
			$desc = mb_substr( $desc, 0, $length ) . ' &hellip;';

			// If not a full sentence, and one ends within 20% of the end, trim it to that.
			if ( function_exists( 'mb_strrpos' ) ) {
				$pos = mb_strrpos( $desc, '.' );
			} else {
				$pos = strrpos( $desc, '.' );
			}
			if ( $pos > ( 0.8 * $length ) && '.' !== mb_substr( $desc, - 1 ) ) {
				$desc = mb_substr( $desc, 0, $pos + 1 );
			}
		}

		return trim( $desc );
	}

}
