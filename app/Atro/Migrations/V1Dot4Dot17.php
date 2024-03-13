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

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot4Dot17 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `translation` DROP ga_ie, DROP gn_py, DROP km_kh, DROP ku_tr, DROP ne_np, DROP ps_af, DROP sr_rs, DROP tl_ph, DROP ur_pk, ADD ar_dz MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_bh MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_eg MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_iq MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_jo MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_kw MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_lb MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_ly MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_ma MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_om MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_qa MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_sa MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_sy MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_tn MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_ae MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ar_ye MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD zh_mo MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD zh_sg MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD nl_be MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_au MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_bz MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_ca MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_ie MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_jm MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_nz MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_ph MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_za MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_tt MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_vi MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD en_zw MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD fr_be MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD fr_lu MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD fr_mc MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD fr_ch MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD de_at MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD de_li MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD de_lu MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD de_ch MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD gu_in MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD it_ch MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD kn_in MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD kk_kz MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD kok_in MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD ms_bn MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD mt_mt MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD mr_in MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD mn_mn MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD se_no MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD sr_ba MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD sr_cs MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_ar MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_bo MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_cl MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_co MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_cr MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_do MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_ec MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_sv MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_gt MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_hn MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_mx MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_ni MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_pa MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_py MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_pe MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_pr MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_uy MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD es_ve MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD sv_fi MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD syr_sy MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD tn_za MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD uz_uz MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD xh_za MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD zu_za MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
    }

    public function down(): void
    {
    }

    protected function execute(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
