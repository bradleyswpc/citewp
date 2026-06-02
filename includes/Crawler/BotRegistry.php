<?php
/**
 * Registry of known AI crawler signatures.
 *
 * Each entry maps a unique user-agent substring to a canonical bot record.
 * Match strings are LOWERCASED for case-insensitive comparison.
 *
 * Sources:
 * - https://platform.openai.com/docs/bots
 * - https://docs.anthropic.com/en/docs/agents-and-tools/claude-bot
 * - https://docs.perplexity.ai/guides/bots
 * - https://developers.google.com/search/docs/crawling-indexing/google-common-crawlers
 * - https://darkvisitors.com (cross-reference)
 *
 * @package CiteWP\Aiso
 */

declare( strict_types=1 );

namespace CiteWP\Aiso\Crawler;

defined( 'ABSPATH' ) || exit;

final class BotRegistry {

	/**
	 * @return array<int, array{match: string, name: string, vendor: string, purpose: string}>
	 */
	public static function all(): array {
		return [
			// --- OpenAI ---
			[ 'match' => 'gptbot',                'name' => 'GPTBot',                'vendor' => 'OpenAI',     'purpose' => 'training' ],
			[ 'match' => 'oai-searchbot',         'name' => 'OAI-SearchBot',         'vendor' => 'OpenAI',     'purpose' => 'search' ],
			[ 'match' => 'chatgpt-user',          'name' => 'ChatGPT-User',          'vendor' => 'OpenAI',     'purpose' => 'user_action' ],

			// --- Anthropic ---
			[ 'match' => 'claudebot',             'name' => 'ClaudeBot',             'vendor' => 'Anthropic',  'purpose' => 'training' ],
			[ 'match' => 'claude-web',            'name' => 'Claude-Web',            'vendor' => 'Anthropic',  'purpose' => 'user_action' ],
			[ 'match' => 'claude-user',           'name' => 'Claude-User',           'vendor' => 'Anthropic',  'purpose' => 'user_action' ],
			[ 'match' => 'claude-searchbot',      'name' => 'Claude-SearchBot',      'vendor' => 'Anthropic',  'purpose' => 'search' ],
			[ 'match' => 'anthropic-ai',          'name' => 'anthropic-ai',          'vendor' => 'Anthropic',  'purpose' => 'training' ],

			// --- Google ---
			[ 'match' => 'google-extended',       'name' => 'Google-Extended',       'vendor' => 'Google',     'purpose' => 'training' ],
			[ 'match' => 'googleother',           'name' => 'GoogleOther',           'vendor' => 'Google',     'purpose' => 'misc' ],
			[ 'match' => 'google-cloudvertexbot', 'name' => 'Google-CloudVertexBot', 'vendor' => 'Google',     'purpose' => 'training' ],

			// --- Perplexity ---
			[ 'match' => 'perplexitybot',         'name' => 'PerplexityBot',         'vendor' => 'Perplexity', 'purpose' => 'search' ],
			[ 'match' => 'perplexity-user',       'name' => 'Perplexity-User',       'vendor' => 'Perplexity', 'purpose' => 'user_action' ],

			// --- Apple ---
			[ 'match' => 'applebot-extended',     'name' => 'Applebot-Extended',     'vendor' => 'Apple',      'purpose' => 'training' ],
			[ 'match' => 'applebot',              'name' => 'Applebot',              'vendor' => 'Apple',      'purpose' => 'search' ],

			// --- Meta ---
			[ 'match' => 'meta-externalagent',    'name' => 'Meta-ExternalAgent',    'vendor' => 'Meta',       'purpose' => 'training' ],
			[ 'match' => 'meta-externalfetcher',  'name' => 'Meta-ExternalFetcher',  'vendor' => 'Meta',       'purpose' => 'user_action' ],
			[ 'match' => 'facebookbot',           'name' => 'FacebookBot',           'vendor' => 'Meta',       'purpose' => 'training' ],

			// --- ByteDance / TikTok ---
			[ 'match' => 'bytespider',            'name' => 'Bytespider',            'vendor' => 'ByteDance',  'purpose' => 'training' ],
			[ 'match' => 'tiktokspider',          'name' => 'TikTokSpider',          'vendor' => 'ByteDance',  'purpose' => 'training' ],

			// --- Microsoft ---
			[ 'match' => 'cccbot',                'name' => 'cccbot',                'vendor' => 'Microsoft',  'purpose' => 'training' ],

			// --- Common Crawl (training data backbone for many models) ---
			[ 'match' => 'ccbot',                 'name' => 'CCBot',                 'vendor' => 'Common Crawl','purpose' => 'training' ],

			// --- Cohere ---
			[ 'match' => 'cohere-ai',             'name' => 'cohere-ai',             'vendor' => 'Cohere',     'purpose' => 'training' ],
			[ 'match' => 'cohere-training-data-crawler', 'name' => 'cohere-training-data-crawler', 'vendor' => 'Cohere', 'purpose' => 'training' ],

			// --- Mistral ---
			[ 'match' => 'mistralai-user',        'name' => 'MistralAI-User',        'vendor' => 'Mistral',    'purpose' => 'user_action' ],

			// --- xAI ---
			[ 'match' => 'grok',                  'name' => 'Grok',                  'vendor' => 'xAI',        'purpose' => 'training' ],

			// --- DuckDuckGo ---
			[ 'match' => 'duckassistbot',         'name' => 'DuckAssistBot',         'vendor' => 'DuckDuckGo', 'purpose' => 'search' ],

			// --- You.com ---
			[ 'match' => 'youbot',                'name' => 'YouBot',                'vendor' => 'You.com',    'purpose' => 'search' ],

			// --- Diffbot ---
			[ 'match' => 'diffbot',               'name' => 'Diffbot',               'vendor' => 'Diffbot',    'purpose' => 'misc' ],

			// --- Amazon ---
			[ 'match' => 'amazonbot',             'name' => 'Amazonbot',             'vendor' => 'Amazon',     'purpose' => 'training' ],

			// --- Quillbot ---
			[ 'match' => 'quillbot',              'name' => 'QuillBot',              'vendor' => 'QuillBot',   'purpose' => 'misc' ],

			// --- Timpi ---
			[ 'match' => 'timpibot',              'name' => 'Timpibot',              'vendor' => 'Timpi',      'purpose' => 'search' ],

			// --- Webz / News crawlers used by AI ---
			[ 'match' => 'omgili',                'name' => 'omgili',                'vendor' => 'Webz.io',    'purpose' => 'training' ],
			[ 'match' => 'omgilibot',             'name' => 'omgilibot',             'vendor' => 'Webz.io',    'purpose' => 'training' ],

			// --- Other notable AI/data crawlers ---
			[ 'match' => 'iaskspider',            'name' => 'iaskspider',            'vendor' => 'iAsk',       'purpose' => 'search' ],
			[ 'match' => 'phindbot',              'name' => 'PhindBot',              'vendor' => 'Phind',      'purpose' => 'search' ],
			[ 'match' => 'kagibot',               'name' => 'KagiBot',               'vendor' => 'Kagi',       'purpose' => 'search' ],
			[ 'match' => 'sidetrade',             'name' => 'Sidetrade',             'vendor' => 'Sidetrade',  'purpose' => 'training' ],
			[ 'match' => 'img2dataset',           'name' => 'img2dataset',           'vendor' => 'Generic',    'purpose' => 'training' ],
			[ 'match' => 'webzio-extended',       'name' => 'Webzio-Extended',       'vendor' => 'Webz.io',    'purpose' => 'training' ],
			[ 'match' => 'scalenut',              'name' => 'Scalenut',              'vendor' => 'Scalenut',   'purpose' => 'misc' ],
			[ 'match' => 'velenpublicwebcrawler', 'name' => 'VelenPublicWebCrawler', 'vendor' => 'Velen',      'purpose' => 'training' ],

			// --- FB58: Bot registry expansion to 86+ verified AI crawlers ---
			// Sources: darkvisitors.com, official bot documentation, Citelayer changelog.

			// --- AI training crawlers ---
			[ 'match' => 'deepseek',              'name' => 'DeepSeek-Bot',              'vendor' => 'DeepSeek',              'purpose' => 'training'    ],
			[ 'match' => 'ai2-bot',               'name' => 'AI2Bot',                    'vendor' => 'Allen Institute for AI', 'purpose' => 'training'    ],
			[ 'match' => 'friendlycrawler',       'name' => 'FriendlyCrawler',           'vendor' => 'Open Training',         'purpose' => 'training'    ],
			[ 'match' => 'kangarobot',            'name' => 'KangarooBot',               'vendor' => 'Knowledge AI',          'purpose' => 'training'    ],
			[ 'match' => 'brightbot',             'name' => 'Brightbot',                 'vendor' => 'Bright Data',           'purpose' => 'training'    ],
			[ 'match' => 'icc-crawler',           'name' => 'ICC-Crawler',               'vendor' => 'ICC',                   'purpose' => 'training'    ],
			[ 'match' => 'turnitin',              'name' => 'TurnitinBot',               'vendor' => 'Turnitin',              'purpose' => 'training'    ],
			[ 'match' => 'ia_archiver',           'name' => 'Heritrix',                  'vendor' => 'Internet Archive',      'purpose' => 'training'    ],
			// Open-source ML pipeline tools — appear in AI-lab crawl pipelines.
			[ 'match' => 'news-please',           'name' => 'news-please',               'vendor' => 'Open Source',           'purpose' => 'training'    ],
			[ 'match' => 'newspaper3k',           'name' => 'newspaper3k',               'vendor' => 'Open Source',           'purpose' => 'training'    ],
			[ 'match' => 'trafilatura',           'name' => 'trafilatura',               'vendor' => 'Open Source',           'purpose' => 'training'    ],
			[ 'match' => 'scrapy',                'name' => 'Scrapy',                    'vendor' => 'Open Source',           'purpose' => 'training'    ],
			// Mistral catch-all: must follow mistralai-user so user_action is matched first.
			[ 'match' => 'mistralai',             'name' => 'MistralAI',                 'vendor' => 'Mistral',               'purpose' => 'training'    ],

			// --- AI search crawlers ---
			[ 'match' => 'brave-search',          'name' => 'Brave-SearchBot',           'vendor' => 'Brave Software',        'purpose' => 'search'      ],
			[ 'match' => 'petalbot',              'name' => 'PetalBot',                  'vendor' => 'Huawei Technologies',   'purpose' => 'search'      ],
			[ 'match' => 'mojeekbot',             'name' => 'MojeekBot',                 'vendor' => 'Mojeek',                'purpose' => 'search'      ],
			[ 'match' => 'neevabot',              'name' => 'NeevaBot',                  'vendor' => 'Neeva',                 'purpose' => 'search'      ],
			[ 'match' => 'yisouspider',           'name' => 'YisouSpider',               'vendor' => 'Qihoo 360',             'purpose' => 'search'      ],
			[ 'match' => 'semrushbot',            'name' => 'SemrushBot',                'vendor' => 'Semrush',               'purpose' => 'search'      ],
			[ 'match' => 'ahrefsbot',             'name' => 'AhrefsBot',                 'vendor' => 'Ahrefs',                'purpose' => 'search'      ],
			[ 'match' => 'mj12bot',               'name' => 'MJ12bot',                   'vendor' => 'Majestic',              'purpose' => 'search'      ],
			[ 'match' => 'dotbot',                'name' => 'DotBot',                    'vendor' => 'Moz',                   'purpose' => 'search'      ],
			[ 'match' => 'dataforseobot',         'name' => 'DataForSeoBot',             'vendor' => 'DataForSEO',            'purpose' => 'search'      ],
			[ 'match' => 'qwantbot',              'name' => 'QwantBot',                  'vendor' => 'Qwant',                 'purpose' => 'search'      ],
			[ 'match' => 'bingbot',               'name' => 'BingBot',                   'vendor' => 'Microsoft',             'purpose' => 'search'      ],
			[ 'match' => 'baiduspider',           'name' => 'Baiduspider',               'vendor' => 'Baidu',                 'purpose' => 'search'      ],
			[ 'match' => 'sogou',                 'name' => 'Sogou Web Spider',          'vendor' => 'Sogou',                 'purpose' => 'search'      ],
			[ 'match' => 'naver',                 'name' => 'NaverBot (Yeti)',            'vendor' => 'Naver',                 'purpose' => 'search'      ],
			[ 'match' => '360spider',             'name' => '360Spider',                 'vendor' => 'Qihoo 360',             'purpose' => 'search'      ],

			// --- Social link-preview bots (AI assistants extract content from these fetches) ---
			// facebot must precede any longer 'facebook*' variant — though none currently conflict.
			[ 'match' => 'facebot',               'name' => 'Facebot',                   'vendor' => 'Meta',                  'purpose' => 'misc'        ],
			[ 'match' => 'twitterbot',            'name' => 'Twitterbot',                'vendor' => 'X / Twitter',           'purpose' => 'misc'        ],
			[ 'match' => 'linkedinbot',           'name' => 'LinkedInBot',               'vendor' => 'LinkedIn',              'purpose' => 'misc'        ],
			[ 'match' => 'slackbot-linkexpanding','name' => 'Slackbot-LinkExpanding',    'vendor' => 'Slack',                 'purpose' => 'misc'        ],
			[ 'match' => 'discordbot',            'name' => 'DiscordBot',                'vendor' => 'Discord',               'purpose' => 'misc'        ],
			[ 'match' => 'telegrambot',           'name' => 'TelegramBot',               'vendor' => 'Telegram',              'purpose' => 'misc'        ],
			[ 'match' => 'whatsapp',              'name' => 'WhatsApp',                  'vendor' => 'Meta',                  'purpose' => 'misc'        ],
			[ 'match' => 'pinterestbot',          'name' => 'PinterestBot',              'vendor' => 'Pinterest',             'purpose' => 'misc'        ],

			// --- Media monitoring / AI analytics ---
			[ 'match' => 'meltwater',             'name' => 'MeltwaterBot',              'vendor' => 'Meltwater',             'purpose' => 'misc'        ],
			[ 'match' => 'brandwatch',            'name' => 'BrandwatchBot',             'vendor' => 'Brandwatch',            'purpose' => 'misc'        ],
			[ 'match' => 'sentibot',              'name' => 'SentiBot',                  'vendor' => 'Senti.io',              'purpose' => 'misc'        ],
			[ 'match' => 'blexbot',               'name' => 'BLEXBot',                   'vendor' => 'Webmeup',               'purpose' => 'misc'        ],
			[ 'match' => 'peer39_crawler',        'name' => 'peer39_crawler',            'vendor' => 'Peer39',                'purpose' => 'misc'        ],
			[ 'match' => 'apify',                 'name' => 'ApifyBot',                  'vendor' => 'Apify',                 'purpose' => 'misc'        ],
		];
	}

	/**
	 * Find the bot record matching a given user-agent string.
	 * Returns null if no match.
	 *
	 * Matching is "first hit wins" — order in all() matters when one bot's
	 * UA contains another's substring (e.g. Applebot-Extended must precede Applebot).
	 *
	 * @return array{match: string, name: string, vendor: string, purpose: string}|null
	 */
	public static function match( string $user_agent ): ?array {
		if ( $user_agent === '' ) {
			return null;
		}
		$ua = strtolower( $user_agent );
		foreach ( self::all() as $bot ) {
			if ( str_contains( $ua, $bot['match'] ) ) {
				return $bot;
			}
		}
		return null;
	}
}
