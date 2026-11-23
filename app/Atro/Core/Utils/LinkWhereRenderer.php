<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Utils;

use Atro\Core\Twig\Twig;
use Espo\ORM\Entity;

/**
 * Renders the Twig expressions of the `where` filter of a link field. The saved record is available as `entity`, and the
 * current values of the form, which are not saved, as `uiRecord`: the same variables as in the expressions of the
 * actions.
 *
 * The filter is rendered at once, as one template. A string that is one expression, e.g. `"{{ uiRecord.name }}"`, is
 * replaced by the value of the expression as JSON, so the value can be a list, a number or null, and a text with quotes
 * does not change the structure of the filter. In a string with a text around its expressions, the expressions are
 * replaced as text. A filter that cannot be rendered is returned as it is.
 */
class LinkWhereRenderer
{
    public function __construct(private readonly Twig $twig)
    {
    }

    /**
     * @param array  $where    The groups of rules of the filter
     * @param Entity $entity   The saved record, or a blank record when the form is not saved yet
     * @param ?array $uiRecord The current values of the form by field, null when there is no form, e.g. when a record is saved
     */
    public function render(array $where, Entity $entity, ?array $uiRecord = null): array
    {
        $json = json_encode($where, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!str_contains($json, '{{') && !str_contains($json, '{%')) {
            return $where;
        }

        $template = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/s', function (array $match): string {
            $value = json_decode($match[0]);

            if (is_string($value) && substr_count($value, '{{') === 1 && preg_match('/^\s*\{\{(.*)\}\}\s*$/s', $value, $expression) === 1) {
                return '{{ (' . $expression[1] . ')|json_encode|raw }}';
            }

            return $match[0];
        }, $json);

        $rendered = $this->twig->renderTemplate(
            '{% autoescape false %}' . $template . '{% endautoescape %}',
            ['entity' => $entity, 'uiRecord' => $uiRecord]
        );

        $result = is_string($rendered) && !str_starts_with($rendered, 'Error: ') ? json_decode($rendered, true) : null;

        return is_array($result) ? $result : $where;
    }
}
