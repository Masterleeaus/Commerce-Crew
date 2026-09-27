<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Support;

final class ListingComplianceScanner
{
    /** @param array<string,mixed> $content @param array<int,array<string,mixed>> $rules @param array<string,mixed> $brandGuide
     * @return array<string,mixed>
     */
    public static function scan(array $content, array $rules, array $brandGuide = []): array
    {
        $findings = [];
        $fields = [];
        foreach ($content as $field => $value) {
            if (is_scalar($value)) $fields[(string) $field] = strtolower((string) $value);
            elseif (is_array($value)) $fields[(string) $field] = strtolower(implode(' ', self::flatten($value)));
        }
        foreach ($rules as $rule) {
            $scopes = array_values((array) ($rule['field_scopes'] ?? array_keys($fields)));
            $terms = array_values((array) ($rule['terms'] ?? []));
            foreach ($scopes as $scope) {
                $haystack = $fields[(string) $scope] ?? '';
                foreach ($terms as $term) {
                    $needle = strtolower(trim((string) $term));
                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        $findings[] = self::finding(
                            (string) ($rule['code'] ?? 'policy_term'),
                            (string) ($rule['severity'] ?? 'warning'),
                            (string) $scope,
                            $needle,
                            (string) ($rule['message'] ?? 'Review this marketplace content.'),
                            (string) ($rule['remediation'] ?? 'Remove or substantiate the flagged wording.')
                        );
                    }
                }
            }
        }
        foreach ((array) ($brandGuide['forbidden_terms'] ?? []) as $term) {
            $needle = strtolower(trim((string) $term));
            foreach ($fields as $field => $haystack) {
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    $findings[] = self::finding('brand_forbidden_term', 'warning', $field, $needle, 'This wording conflicts with the saved brand voice.', 'Replace it with approved brand language.');
                }
            }
        }
        $findings = array_values(array_reduce($findings, static function (array $carry, array $finding): array {
            $key = implode('|', [$finding['code'],$finding['field'],$finding['matched_value']]);
            $carry[$key] = $finding;
            return $carry;
        }, []));
        $blocking = count(array_filter($findings, static fn (array $f): bool => $f['severity'] === 'block'));
        $warnings = count(array_filter($findings, static fn (array $f): bool => $f['severity'] === 'warning'));
        return ['findings'=>$findings,'blocking_count'=>$blocking,'warning_count'=>$warnings,'publishable'=>$blocking === 0];
    }

    /** @param array<mixed> $values @return array<int,string> */
    private static function flatten(array $values): array
    {
        $result=[];
        array_walk_recursive($values, static function ($value) use (&$result): void { if (is_scalar($value)) $result[]=(string)$value; });
        return $result;
    }

    /** @return array<string,string> */
    private static function finding(string $code, string $severity, string $field, string $matched, string $message, string $remediation): array
    {
        return ['code'=>$code,'severity'=>in_array($severity,['info','warning','block'],true)?$severity:'warning','field'=>$field,'matched_value'=>$matched,'message'=>$message,'remediation'=>$remediation];
    }
}
