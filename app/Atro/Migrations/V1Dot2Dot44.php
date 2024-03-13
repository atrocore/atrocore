<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

namespace Atro\Migrations;

use Atro\Console\AbstractConsole;

use Atro\Core\Migration\Base;

/**
 * Migration for version 1.2.44
 */
class V1Dot2Dot44 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
//        rename table label to translation;

        $this->execute(
            "CREATE TABLE `translation` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `module` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `is_customized` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, `en_us` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `de_de` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `es_es` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `fr_fr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ru_ru` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `af_za` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `az_az` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `be_by` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `bg_bg` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `bn_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `bs_ba` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ca_es` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `cs_cz` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `cy_gb` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `da_dk` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `el_gr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `en_gb` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `et_ee` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `eu_es` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `fa_ir` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `fi_fi` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `fo_fo` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `fr_ca` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ga_ie` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `gl_es` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `gn_py` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `he_il` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `hi_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `hr_hr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `hu_hu` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `hy_am` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `id_id` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `is_is` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `it_it` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ja_jp` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ka_ge` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `km_kh` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ko_kr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ku_tr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `lt_lt` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `lv_lv` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `mk_mk` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ml_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ms_my` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `nb_no` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `nn_no` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ne_np` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `nl_nl` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `pa_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `pl_pl` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ps_af` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `pt_br` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `pt_pt` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ro_ro` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sk_sk` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sl_si` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sq_al` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sr_rs` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sv_se` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sw_ke` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ta_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `te_in` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `th_th` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `tl_ph` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `tr_tr` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `uk_ua` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `ur_pk` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `vi_vn` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `zh_cn` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `zh_hk` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `zh_tw` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_NAME` (name, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php refresh translations >/dev/null");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("DROP TABLE `translation`");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
