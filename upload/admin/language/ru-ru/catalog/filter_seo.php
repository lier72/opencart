<?php
// Heading
$_['heading_title']                    = 'SEO фильтров';

// Text
$_['text_success']                     = 'Настройки успешно изменены!';
$_['text_list']                        = 'Facets (атрибуты, опции, группы фильтров)';
$_['text_list_help']                   = 'Слово в скобках "Заменить" применяется к каждому значению как "слово-название-значение", например raketki-tsvet-belyy.';
$_['text_type_fa']                     = 'Атрибут';
$_['text_type_fo']                     = 'Опция';
$_['text_type_ff']                     = 'Фильтр';
$_['text_colliding']                   = 'Название совпадает с другим facet-ом - без замены slug-и будут неоднозначны';
$_['text_colliding_short']             = 'Совпадение';
$_['text_colliding_warning']           = 'Название этого facet-а совпадает с другим - без замены (ниже) сгенерированные ссылки получат числовой суффикс (-2, -3, ...) вместо понятного слова.';
$_['text_configured']                  = 'Настроено';
$_['text_values']                      = 'Значения';
$_['text_values_help']                 = 'Только значения, которые реально используются товарами сейчас. Изменения вступают в силу сразу после сохранения - старые ссылки для изменённых значений перестанут работать.';
$_['text_not_generated']               = '(ещё не создано)';
$_['text_no_results']                  = 'Нет результатов';
$_['text_confirm_regenerate']          = 'Пересоздать все ссылки для этого facet-а сейчас? Старые ссылки перестанут работать.';
$_['text_regenerated']                 = 'Пересоздано %s ссылок.';

// Column
$_['column_type']                      = 'Тип';
$_['column_name']                      = 'Название';
$_['column_group']                     = 'Группа';
$_['column_status']                    = 'Статус';
$_['column_action']                    = 'Действие';
$_['column_value']                     = 'Значение';
$_['column_current_slug']              = 'Текущая ссылка';
$_['column_value_override']            = 'Заменить';

// Entry
$_['entry_prefix_override']            = 'Заменить название facet-а';
$_['entry_prefix_override_placeholder'] = 'например raketki';
$_['entry_omit_facet_name']            = 'Убрать название facet-а полностью из ссылки';
$_['entry_strip']                      = 'Упростить ссылку';
$_['entry_strip_parenthetical']        = 'Убрать "(...)" в конце (например код цвета "(C066)")';
$_['entry_strip_brackets']             = 'Убрать "[...]" в конце (например "[Asia (S)]")';

// Help
$_['help_prefix_override']             = 'По умолчанию используется вместе с настоящим названием facet-а, например "raketki" + "Цвет" = raketki-tsvet-*. Включите "Убрать название facet-а" ниже, если хотите вместо этого только "raketki-белый" или просто "белый".';
$_['help_omit_facet_name']              = 'Например: raketki-tsvet-belyy станет raketki-belyy. Если поле "Заменить название" выше оставить пустым, ссылка будет состоять только из значения, например просто "belyy".';
$_['help_regenerate']                  = 'Пересоздаёт все ссылки этого facet-а сейчас же, а не только по мере посещения сайта.';

// Button
$_['button_edit']                      = 'Редактировать';
$_['button_save']                      = 'Сохранить';
$_['button_cancel']                    = 'Отмена';
$_['button_regenerate']                = 'Пересоздать ссылки сейчас';

// Error
$_['error_permission']                 = 'У Вас нет прав для изменения SEO фильтров!';
$_['error_facet_not_found']            = 'Facet не найден!';
