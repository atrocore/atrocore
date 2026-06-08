<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;

class V2Dot3Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-03 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec(
                "CREATE TABLE translation (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', code VARCHAR(255) DEFAULT NULL, module VARCHAR(255) DEFAULT 'custom', is_customized BOOLEAN DEFAULT 'true' NOT NULL, af_za TEXT DEFAULT NULL, sq_al TEXT DEFAULT NULL, ar_dz TEXT DEFAULT NULL, ar_bh TEXT DEFAULT NULL, ar_eg TEXT DEFAULT NULL, ar_iq TEXT DEFAULT NULL, ar_jo TEXT DEFAULT NULL, ar_kw TEXT DEFAULT NULL, ar_lb TEXT DEFAULT NULL, ar_ly TEXT DEFAULT NULL, ar_ma TEXT DEFAULT NULL, ar_om TEXT DEFAULT NULL, ar_qa TEXT DEFAULT NULL, ar_sa TEXT DEFAULT NULL, ar_sy TEXT DEFAULT NULL, ar_tn TEXT DEFAULT NULL, ar_ae TEXT DEFAULT NULL, ar_ye TEXT DEFAULT NULL, hy_am TEXT DEFAULT NULL, az_az TEXT DEFAULT NULL, eu_es TEXT DEFAULT NULL, be_by TEXT DEFAULT NULL, bn_in TEXT DEFAULT NULL, bs_ba TEXT DEFAULT NULL, bg_bg TEXT DEFAULT NULL, ca_es TEXT DEFAULT NULL, zh_cn TEXT DEFAULT NULL, zh_hk TEXT DEFAULT NULL, zh_mo TEXT DEFAULT NULL, zh_sg TEXT DEFAULT NULL, zh_tw TEXT DEFAULT NULL, hr_hr TEXT DEFAULT NULL, cs_cz TEXT DEFAULT NULL, da_dk TEXT DEFAULT NULL, nl_be TEXT DEFAULT NULL, nl_nl TEXT DEFAULT NULL, en_au TEXT DEFAULT NULL, en_bz TEXT DEFAULT NULL, en_ca TEXT DEFAULT NULL, en_ie TEXT DEFAULT NULL, en_jm TEXT DEFAULT NULL, en_nz TEXT DEFAULT NULL, en_ph TEXT DEFAULT NULL, en_za TEXT DEFAULT NULL, en_tt TEXT DEFAULT NULL, en_vi TEXT DEFAULT NULL, en_gb TEXT DEFAULT NULL, en_us TEXT DEFAULT NULL, en_zw TEXT DEFAULT NULL, et_ee TEXT DEFAULT NULL, fo_fo TEXT DEFAULT NULL, fi_fi TEXT DEFAULT NULL, fr_be TEXT DEFAULT NULL, fr_ca TEXT DEFAULT NULL, fr_fr TEXT DEFAULT NULL, fr_lu TEXT DEFAULT NULL, fr_mc TEXT DEFAULT NULL, fr_ch TEXT DEFAULT NULL, gl_es TEXT DEFAULT NULL, ka_ge TEXT DEFAULT NULL, de_at TEXT DEFAULT NULL, de_de TEXT DEFAULT NULL, de_li TEXT DEFAULT NULL, de_lu TEXT DEFAULT NULL, de_ch TEXT DEFAULT NULL, el_gr TEXT DEFAULT NULL, gu_in TEXT DEFAULT NULL, he_il TEXT DEFAULT NULL, hi_in TEXT DEFAULT NULL, hu_hu TEXT DEFAULT NULL, is_is TEXT DEFAULT NULL, id_id TEXT DEFAULT NULL, it_it TEXT DEFAULT NULL, it_ch TEXT DEFAULT NULL, ja_jp TEXT DEFAULT NULL, kn_in TEXT DEFAULT NULL, kk_kz TEXT DEFAULT NULL, kok_in TEXT DEFAULT NULL, ko_kr TEXT DEFAULT NULL, lv_lv TEXT DEFAULT NULL, lt_lt TEXT DEFAULT NULL, mk_mk TEXT DEFAULT NULL, ms_bn TEXT DEFAULT NULL, ms_my TEXT DEFAULT NULL, ml_in TEXT DEFAULT NULL, mt_mt TEXT DEFAULT NULL, mr_in TEXT DEFAULT NULL, mn_mn TEXT DEFAULT NULL, se_no TEXT DEFAULT NULL, nb_no TEXT DEFAULT NULL, nn_no TEXT DEFAULT NULL, fa_ir TEXT DEFAULT NULL, pl_pl TEXT DEFAULT NULL, pt_br TEXT DEFAULT NULL, pt_pt TEXT DEFAULT NULL, pa_in TEXT DEFAULT NULL, ro_ro TEXT DEFAULT NULL, ru_ru TEXT DEFAULT NULL, sr_ba TEXT DEFAULT NULL, sr_cs TEXT DEFAULT NULL, sk_sk TEXT DEFAULT NULL, sl_si TEXT DEFAULT NULL, es_ar TEXT DEFAULT NULL, es_bo TEXT DEFAULT NULL, es_cl TEXT DEFAULT NULL, es_co TEXT DEFAULT NULL, es_cr TEXT DEFAULT NULL, es_do TEXT DEFAULT NULL, es_ec TEXT DEFAULT NULL, es_sv TEXT DEFAULT NULL, es_gt TEXT DEFAULT NULL, es_hn TEXT DEFAULT NULL, es_mx TEXT DEFAULT NULL, es_ni TEXT DEFAULT NULL, es_pa TEXT DEFAULT NULL, es_py TEXT DEFAULT NULL, es_pe TEXT DEFAULT NULL, es_pr TEXT DEFAULT NULL, es_es TEXT DEFAULT NULL, es_uy TEXT DEFAULT NULL, es_ve TEXT DEFAULT NULL, sw_ke TEXT DEFAULT NULL, sv_fi TEXT DEFAULT NULL, sv_se TEXT DEFAULT NULL, syr_sy TEXT DEFAULT NULL, ta_in TEXT DEFAULT NULL, te_in TEXT DEFAULT NULL, th_th TEXT DEFAULT NULL, tn_za TEXT DEFAULT NULL, tr_tr TEXT DEFAULT NULL, uk_ua TEXT DEFAULT NULL, uz_uz TEXT DEFAULT NULL, vi_vn TEXT DEFAULT NULL, cy_gb TEXT DEFAULT NULL, xh_za TEXT DEFAULT NULL, zu_za TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))"
            );
            $this->exec("CREATE UNIQUE INDEX UNIQ_B469456F77153098EB3B4E33 ON translation (code, deleted)");
            $this->exec("CREATE INDEX IDX_TRANSLATION_CREATED_BY_ID ON translation (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_TRANSLATION_MODIFIED_BY_ID ON translation (modified_by_id, deleted)");
        } else {
            $this->exec(
                "CREATE TABLE translation (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', code VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin DEFAULT NULL, module VARCHAR(255) DEFAULT 'custom', is_customized TINYINT(1) DEFAULT '1' NOT NULL, af_za LONGTEXT DEFAULT NULL, sq_al LONGTEXT DEFAULT NULL, ar_dz LONGTEXT DEFAULT NULL, ar_bh LONGTEXT DEFAULT NULL, ar_eg LONGTEXT DEFAULT NULL, ar_iq LONGTEXT DEFAULT NULL, ar_jo LONGTEXT DEFAULT NULL, ar_kw LONGTEXT DEFAULT NULL, ar_lb LONGTEXT DEFAULT NULL, ar_ly LONGTEXT DEFAULT NULL, ar_ma LONGTEXT DEFAULT NULL, ar_om LONGTEXT DEFAULT NULL, ar_qa LONGTEXT DEFAULT NULL, ar_sa LONGTEXT DEFAULT NULL, ar_sy LONGTEXT DEFAULT NULL, ar_tn LONGTEXT DEFAULT NULL, ar_ae LONGTEXT DEFAULT NULL, ar_ye LONGTEXT DEFAULT NULL, hy_am LONGTEXT DEFAULT NULL, az_az LONGTEXT DEFAULT NULL, eu_es LONGTEXT DEFAULT NULL, be_by LONGTEXT DEFAULT NULL, bn_in LONGTEXT DEFAULT NULL, bs_ba LONGTEXT DEFAULT NULL, bg_bg LONGTEXT DEFAULT NULL, ca_es LONGTEXT DEFAULT NULL, zh_cn LONGTEXT DEFAULT NULL, zh_hk LONGTEXT DEFAULT NULL, zh_mo LONGTEXT DEFAULT NULL, zh_sg LONGTEXT DEFAULT NULL, zh_tw LONGTEXT DEFAULT NULL, hr_hr LONGTEXT DEFAULT NULL, cs_cz LONGTEXT DEFAULT NULL, da_dk LONGTEXT DEFAULT NULL, nl_be LONGTEXT DEFAULT NULL, nl_nl LONGTEXT DEFAULT NULL, en_au LONGTEXT DEFAULT NULL, en_bz LONGTEXT DEFAULT NULL, en_ca LONGTEXT DEFAULT NULL, en_ie LONGTEXT DEFAULT NULL, en_jm LONGTEXT DEFAULT NULL, en_nz LONGTEXT DEFAULT NULL, en_ph LONGTEXT DEFAULT NULL, en_za LONGTEXT DEFAULT NULL, en_tt LONGTEXT DEFAULT NULL, en_vi LONGTEXT DEFAULT NULL, en_gb LONGTEXT DEFAULT NULL, en_us LONGTEXT DEFAULT NULL, en_zw LONGTEXT DEFAULT NULL, et_ee LONGTEXT DEFAULT NULL, fo_fo LONGTEXT DEFAULT NULL, fi_fi LONGTEXT DEFAULT NULL, fr_be LONGTEXT DEFAULT NULL, fr_ca LONGTEXT DEFAULT NULL, fr_fr LONGTEXT DEFAULT NULL, fr_lu LONGTEXT DEFAULT NULL, fr_mc LONGTEXT DEFAULT NULL, fr_ch LONGTEXT DEFAULT NULL, gl_es LONGTEXT DEFAULT NULL, ka_ge LONGTEXT DEFAULT NULL, de_at LONGTEXT DEFAULT NULL, de_de LONGTEXT DEFAULT NULL, de_li LONGTEXT DEFAULT NULL, de_lu LONGTEXT DEFAULT NULL, de_ch LONGTEXT DEFAULT NULL, el_gr LONGTEXT DEFAULT NULL, gu_in LONGTEXT DEFAULT NULL, he_il LONGTEXT DEFAULT NULL, hi_in LONGTEXT DEFAULT NULL, hu_hu LONGTEXT DEFAULT NULL, is_is LONGTEXT DEFAULT NULL, id_id LONGTEXT DEFAULT NULL, it_it LONGTEXT DEFAULT NULL, it_ch LONGTEXT DEFAULT NULL, ja_jp LONGTEXT DEFAULT NULL, kn_in LONGTEXT DEFAULT NULL, kk_kz LONGTEXT DEFAULT NULL, kok_in LONGTEXT DEFAULT NULL, ko_kr LONGTEXT DEFAULT NULL, lv_lv LONGTEXT DEFAULT NULL, lt_lt LONGTEXT DEFAULT NULL, mk_mk LONGTEXT DEFAULT NULL, ms_bn LONGTEXT DEFAULT NULL, ms_my LONGTEXT DEFAULT NULL, ml_in LONGTEXT DEFAULT NULL, mt_mt LONGTEXT DEFAULT NULL, mr_in LONGTEXT DEFAULT NULL, mn_mn LONGTEXT DEFAULT NULL, se_no LONGTEXT DEFAULT NULL, nb_no LONGTEXT DEFAULT NULL, nn_no LONGTEXT DEFAULT NULL, fa_ir LONGTEXT DEFAULT NULL, pl_pl LONGTEXT DEFAULT NULL, pt_br LONGTEXT DEFAULT NULL, pt_pt LONGTEXT DEFAULT NULL, pa_in LONGTEXT DEFAULT NULL, ro_ro LONGTEXT DEFAULT NULL, ru_ru LONGTEXT DEFAULT NULL, sr_ba LONGTEXT DEFAULT NULL, sr_cs LONGTEXT DEFAULT NULL, sk_sk LONGTEXT DEFAULT NULL, sl_si LONGTEXT DEFAULT NULL, es_ar LONGTEXT DEFAULT NULL, es_bo LONGTEXT DEFAULT NULL, es_cl LONGTEXT DEFAULT NULL, es_co LONGTEXT DEFAULT NULL, es_cr LONGTEXT DEFAULT NULL, es_do LONGTEXT DEFAULT NULL, es_ec LONGTEXT DEFAULT NULL, es_sv LONGTEXT DEFAULT NULL, es_gt LONGTEXT DEFAULT NULL, es_hn LONGTEXT DEFAULT NULL, es_mx LONGTEXT DEFAULT NULL, es_ni LONGTEXT DEFAULT NULL, es_pa LONGTEXT DEFAULT NULL, es_py LONGTEXT DEFAULT NULL, es_pe LONGTEXT DEFAULT NULL, es_pr LONGTEXT DEFAULT NULL, es_es LONGTEXT DEFAULT NULL, es_uy LONGTEXT DEFAULT NULL, es_ve LONGTEXT DEFAULT NULL, sw_ke LONGTEXT DEFAULT NULL, sv_fi LONGTEXT DEFAULT NULL, sv_se LONGTEXT DEFAULT NULL, syr_sy LONGTEXT DEFAULT NULL, ta_in LONGTEXT DEFAULT NULL, te_in LONGTEXT DEFAULT NULL, th_th LONGTEXT DEFAULT NULL, tn_za LONGTEXT DEFAULT NULL, tr_tr LONGTEXT DEFAULT NULL, uk_ua LONGTEXT DEFAULT NULL, uz_uz LONGTEXT DEFAULT NULL, vi_vn LONGTEXT DEFAULT NULL, cy_gb LONGTEXT DEFAULT NULL, xh_za LONGTEXT DEFAULT NULL, zu_za LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX UNIQ_B469456F77153098EB3B4E33 (code, deleted), INDEX IDX_TRANSLATION_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_TRANSLATION_MODIFIED_BY_ID (modified_by_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB"
            );
        }

        $this->insertCustomTranslations();
    }

    private function insertCustomTranslations(): void
    {
        $filePath = 'data/reference-data/Translation.json';
        if (!file_exists($filePath)) {
            return;
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (empty($data)) {
            return;
        }

        $customRecords = array_filter($data, fn($row) => !empty($row['isCustomized']));
        if (empty($customRecords)) {
            return;
        }

        $rows = [];
        foreach ($customRecords as $record) {
            $row = [];
            foreach ($record as $key => $value) {
                if ($key === 'id') {
                    continue;
                }
                $row[Util::toUnderScore($key)] = $value;
            }
            $row['id'] = IdGenerator::uuid();
            $rows[] = $row;
        }

        $conn = $this->getDbal();

        $allColumns = [
            'id', 'code', 'module', 'is_customized', 'created_at', 'modified_at', 'created_by_id', 'modified_by_id',
            'af_za', 'sq_al', 'ar_dz', 'ar_bh', 'ar_eg', 'ar_iq', 'ar_jo', 'ar_kw', 'ar_lb', 'ar_ly', 'ar_ma', 'ar_om',
            'ar_qa', 'ar_sa', 'ar_sy', 'ar_tn', 'ar_ae', 'ar_ye', 'hy_am', 'az_az', 'eu_es', 'be_by', 'bn_in', 'bs_ba',
            'bg_bg', 'ca_es', 'zh_cn', 'zh_hk', 'zh_mo', 'zh_sg', 'zh_tw', 'hr_hr', 'cs_cz', 'da_dk', 'nl_be', 'nl_nl',
            'en_au', 'en_bz', 'en_ca', 'en_ie', 'en_jm', 'en_nz', 'en_ph', 'en_za', 'en_tt', 'en_vi', 'en_gb', 'en_us', 'en_zw',
            'et_ee', 'fo_fo', 'fi_fi', 'fr_be', 'fr_ca', 'fr_fr', 'fr_lu', 'fr_mc', 'fr_ch', 'gl_es', 'ka_ge',
            'de_at', 'de_de', 'de_li', 'de_lu', 'de_ch', 'el_gr', 'gu_in', 'he_il', 'hi_in', 'hu_hu', 'is_is', 'id_id',
            'it_it', 'it_ch', 'ja_jp', 'kn_in', 'kk_kz', 'kok_in', 'ko_kr', 'lv_lv', 'lt_lt', 'mk_mk', 'ms_bn', 'ms_my',
            'ml_in', 'mt_mt', 'mr_in', 'mn_mn', 'se_no', 'nb_no', 'nn_no', 'fa_ir', 'pl_pl', 'pt_br', 'pt_pt', 'pa_in',
            'ro_ro', 'ru_ru', 'sr_ba', 'sr_cs', 'sk_sk', 'sl_si', 'es_ar', 'es_bo', 'es_cl', 'es_co', 'es_cr', 'es_do',
            'es_ec', 'es_sv', 'es_gt', 'es_hn', 'es_mx', 'es_ni', 'es_pa', 'es_py', 'es_pe', 'es_pr', 'es_es', 'es_uy', 'es_ve',
            'sw_ke', 'sv_fi', 'sv_se', 'syr_sy', 'ta_in', 'te_in', 'th_th', 'tn_za', 'tr_tr', 'uk_ua', 'uz_uz', 'vi_vn',
            'cy_gb', 'xh_za', 'zu_za',
        ];

        foreach (array_chunk($rows, 500) as $chunk) {
            $quotedColumns = array_map(fn($c) => $conn->quoteIdentifier($c), $allColumns);
            $rowPlaceholders = [];
            $params = [];
            $types = [];

            foreach ($chunk as $row) {
                $placeholders = [];
                foreach ($allColumns as $col) {
                    $value = $row[$col] ?? null;
                    $placeholders[] = '?';
                    $params[] = $value;
                    if (is_bool($value)) {
                        $types[] = ParameterType::BOOLEAN;
                    } elseif ($value === null) {
                        $types[] = ParameterType::NULL;
                    } else {
                        $types[] = ParameterType::STRING;
                    }
                }
                $rowPlaceholders[] = '(' . implode(', ', $placeholders) . ')';
            }

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $conn->quoteIdentifier('translation'),
                implode(', ', $quotedColumns),
                implode(', ', $rowPlaceholders)
            );

            try {
                $conn->executeStatement($sql, $params, $types);
            } catch (UniqueConstraintViolationException $e) {
            }
        }

        unlink($filePath);
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
