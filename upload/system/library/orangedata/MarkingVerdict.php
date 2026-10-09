<?php
/**
 * MarkingVerdict
 *
 * Derives the разрешительный-режим sale decision (isSaleAllowed + reason) from the RAW per-code flags returned
 * by a check backend that does not pre-compute it — i.e. ЛМ ЧЗ `/api/v1/cis/check`. (OrangeData ТС ПИоТ returns
 * `checkResult.isSaleAllowed` itself, so this class is not used on that path.)
 *
 * Rules: ЦРПТ «Методические рекомендации», §4 «Определение случаев запрета продажи» (all товарные группы):
 *   valid=false      → 0 «Код маркировки имеет некорректный формат»
 *   found=false      → 1 «Код маркировки не найден в ГИС МТ»
 *   utilised=false   → 2 «Нет информации о нанесении кода»
 *   verified=false   → 3 «Код маркировки не является подлинным»
 *   sold=true        → 4 «Код маркировки выведен из оборота»
 *   realizable=false (and not sold) → 5 «Код маркировки не введён в оборот»
 *   isBlocked=true   → 6 «Код маркировки заблокирован» (ОГВ; applies offline too)
 * Reason codes match OrangeData `checkResult.reason.code`, so the order panel shows them identically.
 * expireDate / МРЦ rules (dairy, beer, water, tobacco) are not applied — the store sells обувь/одежда only.
 *
 * Offline answers (ЛМ ЧЗ local DB, `isCheckedOffline=true`) are decided on the black-list fields alone
 * (isBlocked, sold). Real offline answers still CARRY found/valid/verified/utilised/realizable, all set to
 * false because the local DB does not know them — evaluating those would deny every offline sale. Verified on
 * the АО ЕСП ТС ПИоТ emulator, ПМСР scenarios 5.10–5.13 (esm-emu.ao-esp.ru/mark/210..213).
 * A flag that is ABSENT is never a reason to deny.
 *
 * Scope: called by MarkingCheckResult::normalize() for every checked code; pure and unit-testable.
 */
class MarkingVerdict {

    /**
     * evaluate
     *
     * @param array $code    One element of the backend's `codes[]`.
     * @param bool  $offline True when the answer came from the local black list (isCheckedOffline / inst).
     * @return array ['allowed' => bool, 'reason_code' => int|null, 'reason_text' => string]
     */
    public static function evaluate(array $code, $offline = false) {
        $deny = function ($reasonCode, $text) {
            return array('allowed' => false, 'reason_code' => $reasonCode, 'reason_text' => $text);
        };

        if ($offline) {
            if (!empty($code['isBlocked'])) {
                return $deny(6, 'Код маркировки заблокирован (офлайн-проверка ЛМ ЧЗ)');
            }
            if (!empty($code['sold'])) {
                return $deny(4, 'Код маркировки выведен из оборота (офлайн-проверка ЛМ ЧЗ)');
            }
            return array('allowed' => true, 'reason_code' => null, 'reason_text' => '');
        }

        // Order matters: report the most fundamental problem first.
        if (array_key_exists('valid', $code) && $code['valid'] === false) {
            return $deny(0, 'Код маркировки имеет некорректный формат');
        }
        if (array_key_exists('found', $code) && $code['found'] === false) {
            return $deny(1, 'Код маркировки не найден в ГИС МТ');
        }
        if (array_key_exists('utilised', $code) && $code['utilised'] === false) {
            return $deny(2, 'В ГИС МТ нет информации о нанесении кода маркировки');
        }
        if (array_key_exists('verified', $code) && $code['verified'] === false) {
            return $deny(3, 'Код маркировки не является подлинным');
        }
        if (!empty($code['isBlocked'])) {
            $ogv = !empty($code['ogvs']) ? ' (' . implode(', ', (array) $code['ogvs']) . ')' : '';
            return $deny(6, 'Код маркировки заблокирован' . $ogv);
        }
        if (!empty($code['sold'])) {
            return $deny(4, 'Код маркировки выведен из оборота');
        }
        if (array_key_exists('realizable', $code) && $code['realizable'] === false) {
            return $deny(5, 'Код маркировки не введён в оборот');
        }

        return array('allowed' => true, 'reason_code' => null, 'reason_text' => '');
    }
}
