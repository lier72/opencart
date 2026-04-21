<?php
$_['heading_title']                   = 'Запрос отзыва';

$_['text_extension']                  = 'Расширения';
$_['text_success']                    = 'Настройки модуля запросов отзывов успешно изменены!';
$_['text_edit']                       = 'Настройки модуля запросов отзывов';
$_['text_enabled']                    = 'Включено';
$_['text_disabled']                   = 'Отключено';
$_['text_general']                    = 'Общие настройки';
$_['text_google']                     = 'Google';
$_['text_yandex']                     = 'Яндекс';
$_['text_email']                      = 'Email-цепочка';
$_['text_storefront']                 = 'Витрина';
$_['text_cron']                       = 'Cron';
$_['text_product_only']                = 'Отправлять только отзывы о товарах';
$_['text_skip_email']                  = 'Пропускать письмо';

$_['entry_status']                    = 'Статус модуля';
$_['entry_email_status']              = 'Отправлять email';
$_['entry_show_on_order_page']        = 'Показывать на странице заказа';
$_['entry_delay_days']                = 'Задержка перед отправкой';
$_['entry_order_statuses']            = 'Статусы заказа для постановки в очередь';
$_['entry_include_product_reviews']   = 'Добавлять ссылки на отзывы о товарах';
$_['entry_org_review_cooldown_days']   = 'Пауза между запросами отзыва об организации';
$_['entry_org_review_suppressed_mode'] = 'Что делать во время паузы';
$_['entry_track_review_clicks']        = 'Отслеживать клики по кнопкам отзыва';
$_['entry_email_subject']              = 'Тема письма';
$_['entry_email_body']                 = 'Тело письма';
$_['entry_google_status']             = 'Кнопка отзыва Google';
$_['entry_google_reference']          = 'Google reference';
$_['entry_google_review_url']         = 'Ссылка на отзыв Google';
$_['entry_google_widget_code']        = 'HTML-код SmartWidgets для Google';
$_['entry_yandex_status']             = 'Кнопка отзыва Яндекс';
$_['entry_yandex_reference']          = 'Yandex reference / OID';
$_['entry_yandex_review_url']         = 'Ссылка на отзыв Яндекс';
$_['entry_yandex_widget_code']        = 'HTML-код SmartWidgets для Яндекс';

$_['help_delay_days']                 = 'Через сколько дней после выбранного статуса отправлять письмо.';
$_['help_order_statuses']             = 'Когда заказ получает один из этих статусов, модуль ставит письмо с запросом отзыва в очередь.';
$_['help_org_review_cooldown_days']    = 'Не запрашивать отзыв об организации повторно у того же email, пока не пройдет указанное количество дней. Отзывы о товарах могут отправляться отдельно в зависимости от следующей настройки.';
$_['help_org_review_suppressed_mode']  = 'Выберите, что делать с последующими заказами, пока еще действует пауза на отзыв об организации.';
$_['help_track_review_clicks']         = 'Проводить кнопки Google и Яндекс из email через локальный redirect. Клик по кнопке запускает или обновляет паузу для этого email.';
$_['help_email_subject']               = 'Редактируемый шаблон темы письма с запросом отзыва.';
$_['help_email_body']                  = 'Редактируемый HTML-шаблон тела письма с запросом отзыва.';
$_['help_email_placeholders']          = 'Доступные плейсхолдеры: {store_name}, {order_id}, {order_date}, {customer_firstname}, {customer_lastname}, {customer_name}, {email_intro}, {organization_review_section}, {review_buttons}, {google_button}, {yandex_button}, {google_review_url}, {yandex_review_url}, {product_reviews_section}, {order_button}, {order_link}.';
$_['help_google_reference']           = 'Сохраните URL карточки организации в Google Maps или reference/place ID. Если поле ссылки на отзыв пустое и здесь указан полный URL, он будет использован для кнопки.';
$_['help_google_review_url']          = 'Прямая ссылка для клиента на оставление отзыва в Google. Эта кнопка показывается первой в письме и в блоке заказа.';
$_['help_yandex_reference']           = 'Сохраните URL организации в Яндекс Картах или OID. Если поле ссылки на отзыв пустое и здесь указан полный URL или OID, он будет использован автоматически.';
$_['help_yandex_review_url']          = 'Прямая ссылка для клиента на оставление отзыва в Яндекс. Эта кнопка показывается первой в письме и в блоке заказа.';
$_['help_widget_code']                = 'Вставьте сюда HTML-код установки или iframe из SmartWidgets. Этот код выводится в витринном блоке модуля.';
$_['help_layout']                     = 'Основной акцент делается на отзывы об организации в Google или Яндекс. Ссылки на отзывы о товарах остаются вторичными. Чтобы выводить виджет на обычных страницах витрины, добавьте модуль в макет через Дизайн > Макеты.';
$_['help_cron']                       = 'Запускайте эту команду ежедневно через cron. Скрипт отправляет письма из очереди и повторяет неудачные попытки до 3 раз.';

$_['error_permission']                = 'Внимание: У вас нет прав для изменения модуля запросов отзывов!';
$_['error_delay_days']                = 'Задержка должна быть нулем или положительным целым числом.';
$_['error_org_cooldown_days']          = 'Пауза должна быть нулем или положительным целым числом.';
