<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace Latte\Compiler;

use Latte;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\ContentType;
use Latte\Runtime\Filters;


/**
 * Context-aware escaping.
 */
final class Escaper
{
	use Latte\Strict;

	public const
		Text = 'text',
		JavaScript = 'js',
		Css = 'css',
		ICal = 'ical';

	public const
		Html = 'html',
		HtmlText = '',
		HtmlComment = 'Comment',
		HtmlBogusTag = 'Bogus',
		HtmlCss = 'Css',
		HtmlJavaScript = 'Js',
		HtmlTag = 'Tag',
		HtmlAttribute = 'Attr',
		HtmlAttributeJavaScript = 'AttrJs',
		HtmlAttributeCss = 'AttrCss',
		HtmlAttributeUrl = 'AttrUrl',
		HtmlAttributeUnquotedUrl = 'AttrUnquotedUrl';

	public const
		Xml = 'xml',
		XmlText = '',
		XmlComment = 'Comment',
		XmlBogusTag = 'Bogus',
		XmlTag = 'Tag',
		XmlAttribute = 'Attr';

	private string $state = '';
	private string $tag = '';


	public function __construct(
		private string $contentType,
	) {
	}


	public function getContentType(): string
	{
		return $this->contentType;
	}


	public function export(): string
	{
		return $this->contentType . $this->state;
	}


	public function enterContentType(string $type): void
	{
		$this->contentType = $type;
		$this->state = '';
	}


	public function enterHtmlText(?ElementNode $node): void
	{
		$this->state = self::HtmlText;
		if ($this->contentType === ContentType::Html && $node) {
			$name = strtolower($node->name);
			if (
				($name === 'script' || $name === 'style')
				&& is_string($attr = $node->getAttribute('type') ?? 'css')
				&& preg_match('#(java|j|ecma|live)script|module|json|css|plain#i', $attr)
			) {
				$this->state = $name === 'script'
					? self::HtmlJavaScript
					: self::HtmlCss;
			}
		}
	}


	public function enterHtmlTag(string $name): void
	{
		$this->state = self::HtmlTag;
		$this->tag = strtolower($name);
	}


	public function enterHtmlAttribute(string $name, ?string $quote): void
	{
		if ($this->contentType !== ContentType::Html) {
			$this->state = $quote ? self::XmlAttribute : self::XmlTag;
			return;
		}

		$name = strtolower($name);
		if ($quote) {
			$this->state = self::HtmlAttribute;
			if (str_starts_with($name, 'on')) {
				$this->state = self::HtmlAttributeJavaScript;
			} elseif ($name === 'style') {
				$this->state = self::HtmlAttributeCss;
			}
		} else {
			$this->state = self::HtmlTag;
		}

		if ((in_array($name, ['href', 'src', 'action', 'formaction'], true)
			|| ($name === 'data' && $this->tag === 'object'))
		) {
			$this->state = $this->state === self::HtmlTag
				? self::HtmlAttributeUnquotedUrl
				: self::HtmlAttributeUrl;
		}
	}


	public function enterHtmlBogusTag(): void
	{
		$this->state = self::HtmlBogusTag;
	}


	public function enterHtmlComment(): void
	{
		$this->state = self::HtmlComment;
	}


	public static function getConvertor(string $source, string $dest): ?callable
	{
		$table = [
			self::Text => [
				'html' => 'escapeHtmlText',
				'htmlAttr' => 'escapeHtmlAttr',
				'htmlAttrJs' => 'escapeHtmlAttr',
				'htmlAttrCss' => 'escapeHtmlAttr',
				'htmlAttrUrl' => 'escapeHtmlAttr',
				'htmlComment' => 'escapeHtmlComment',
				'xml' => 'escapeXml',
				'xmlAttr' => 'escapeXml',
			],
			self::JavaScript => [
				'html' => 'escapeHtmlText',
				'htmlAttr' => 'escapeHtmlAttr',
				'htmlAttrJs' => 'escapeHtmlAttr',
				'htmlJs' => 'escapeHtmlRawText',
				'htmlComment' => 'escapeHtmlComment',
			],
			self::Css => [
				'html' => 'escapeHtmlText',
				'htmlAttr' => 'escapeHtmlAttr',
				'htmlAttrCss' => 'escapeHtmlAttr',
				'htmlCss' => 'escapeHtmlRawText',
				'htmlComment' => 'escapeHtmlComment',
			],
			self::Html => [
				'htmlAttr' => 'convertHtmlToHtmlAttr',
				'htmlAttrJs' => 'convertHtmlToHtmlAttr',
				'htmlAttrCss' => 'convertHtmlToHtmlAttr',
				'htmlAttrUrl' => 'convertHtmlToHtmlAttr',
				'htmlComment' => 'escapeHtmlComment',
			],
		];
		return isset($table[$source][$dest])
			? [Filters::class, $table[$source][$dest]]
			: null;
	}
}