<?php
// Heading
$_['heading_title']    = 'Менеджер Бонусов';

// Text
$_['text_extension']   = 'Расширения';
$_['text_success']     = 'Настройки модуля обновлены!';
$_['text_edit']        = 'Настройки модуля бонусов';
$_['text_enabled']     = 'Включено';
$_['text_disabled']    = 'Отключено';
$_['text_bonus_setting_added']   = 'Настройка бонусов добавлена!';
$_['text_bonus_setting_deleted'] = 'Настройка бонусов удалена!';

// Entry
$_['entry_status']              = 'Статус модуля';
$_['entry_discount_threshold']  = 'Порог скидки (%)';
$_['entry_max_usage_percent']   = 'Макс. использование бонусов (%)';
$_['entry_expiration_days']     = 'Срок действия бонусов (дней)';
$_['entry_excluded_categories'] = 'Исключенные категории';
$_['entry_accrual_status']      = 'Статус для начисления';
$_['entry_return_deduction_status'] = 'Статус возврата для списания';
$_['entry_customer_group']      = 'Группа покупателей';
$_['entry_category']            = 'Категория';
$_['entry_bonus_percent']       = 'Процент бонусов';
$_['entry_notification_email']  = 'Email уведомления';
$_['entry_email_awarded_status'] = 'Уведомление о начислении';
$_['entry_email_awarded_subject'] = 'Тема письма (начисление)';
$_['entry_email_awarded_body']   = 'Шаблон письма (начисление)';
$_['entry_email_spent_status']   = 'Уведомление об использовании';
$_['entry_email_spent_subject']  = 'Тема письма (использование)';
$_['entry_email_spent_body']     = 'Шаблон письма (использование)';
$_['entry_email_deducted_status'] = 'Уведомление о возврате';
$_['entry_email_deducted_subject'] = 'Тема письма (возврат)';
$_['entry_email_deducted_body']   = 'Шаблон письма (возврат)';
$_['entry_email_expiring_status'] = 'Предупреждение о сгорании';
$_['entry_email_expiring_subject'] = 'Тема письма (сгорание)';
$_['entry_email_expiring_body']  = 'Шаблон письма (сгорание)';
$_['entry_expiration_warning_days'] = 'Дни предупреждения (через запятую)';
$_['entry_register_widget_heading'] = 'Виджет регистрации';
$_['entry_register_widget_title'] = 'Заголовок виджета';
$_['entry_register_widget_description'] = 'Описание виджета';
$_['entry_register_widget_button_text'] = 'Текст кнопки';
$_['entry_register_widget_icon'] = 'Иконка (Font Awesome)';
$_['entry_register_widget_show_details'] = 'Показывать детали преимуществ';

// Tab
$_['tab_general']               = 'Основные настройки';
$_['tab_bonus_settings']        = 'Настройки бонусов';
$_['tab_notifications']         = 'Уведомления';
$_['tab_statistics']            = 'Статистика';

// Column
$_['column_customer_group']     = 'Группа покупателей';
$_['column_category']           = 'Категория';
$_['column_bonus_percent']      = 'Процент бонусов';
$_['column_action']             = 'Действие';
$_['column_order_id']           = '№ Заказа';
$_['column_customer']           = 'Покупатель';
$_['column_points']             = 'Бонусы';
$_['column_date']               = 'Дата';

// Help
$_['help_status']               = 'Включить или отключить систему бонусов';
$_['help_discount_threshold']   = 'Товары со скидкой больше этого процента не получают бонусы (по умолчанию 15%)';
$_['help_max_usage_percent']    = 'Максимальный процент от суммы корзины, который можно оплатить бонусами (по умолчанию 30%)';
$_['help_expiration_days']      = 'Количество дней, через которое бонусы сгорают (0 = бессрочно, по умолчанию 365)';
$_['help_excluded_categories']  = 'Товары из этих категорий не будут получать бонусы';
$_['help_accrual_status']       = 'Бонусы начисляются когда заказ получает этот статус';
$_['help_return_deduction_status'] = 'Бонусы автоматически списываются когда возврат получает этот статус (по умолчанию: Завершено)';
$_['help_notification_email']   = 'Настройка email уведомлений для различных событий бонусной системы';
$_['help_email_awarded_status'] = 'Отправлять email уведомление покупателю при начислении бонусов';
$_['help_email_awarded_subject'] = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {bonus_amount}, {current_balance}, {store_name}';
$_['help_email_awarded_body']   = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {bonus_amount}, {current_balance}, {max_usage_percent}, {store_name}, {date_awarded}, {account_url}, {order_url}, {store_url}. Поддерживается HTML.';
$_['help_email_spent_status']   = 'Отправлять email уведомление покупателю при использовании бонусов для оплаты заказа';
$_['help_email_spent_subject']  = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {points_spent}, {current_balance}, {store_name}';
$_['help_email_spent_body']     = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {points_spent}, {current_balance}, {store_name}, {date_spent}, {account_url}, {order_url}, {store_url}. Поддерживается HTML.';
$_['help_email_deducted_status'] = 'Отправлять email уведомление покупателю при списании бонусов из-за возврата товара';
$_['help_email_deducted_subject'] = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {return_id}, {points_deducted}, {current_balance}, {store_name}';
$_['help_email_deducted_body']   = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {order_id}, {return_id}, {points_deducted}, {current_balance}, {store_name}, {date_deducted}, {account_url}, {store_url}. Поддерживается HTML.';
$_['help_email_expiring_status'] = 'Отправлять email уведомление когда бонусы скоро сгорят';
$_['help_email_expiring_subject'] = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {expiring_points}, {days_left}, {expiration_date}, {current_balance}, {store_name}';
$_['help_email_expiring_body']   = 'Доступные переменные: {customer_firstname}, {customer_lastname}, {expiring_points}, {days_left}, {expiration_date}, {current_balance}, {store_name}, {account_url}, {store_url}. Поддерживается синтаксис Twig для логики ({% if %}, {% for %}, и т.д.). Поддерживается HTML.';
$_['help_expiration_warning_days'] = 'Отправлять предупреждения за X дней до сгорания (например, "90,30,7" для предупреждений за 90, 30 и 7 дней)';
$_['help_register_widget'] = 'Настройка виджета регистрации, который показывается гостям в корзине. Побуждает посетителей зарегистрироваться и получать бонусные баллы.';
$_['help_register_widget_icon'] = 'Класс иконки Font Awesome (например, fa-gift, fa-star, fa-trophy). См. <a href="https://fontawesome.com/v4.7.0/icons/" target="_blank">иконки Font Awesome</a>';
$_['help_register_widget_show_details'] = 'Показывать список преимуществ (% начисления, % использования, срок действия и т.д.)';

// Button
$_['button_add_setting']        = 'Добавить настройку';
$_['button_save']               = 'Сохранить';
$_['button_cancel']             = 'Отмена';

// Statistics
$_['text_total_issued']         = 'Всего начислено';
$_['text_total_redeemed']       = 'Всего использовано';
$_['text_active_bonuses']       = 'Активных бонусов';
$_['text_customers_count']      = 'Покупателей с бонусами';
$_['text_orders_with_bonuses']  = 'Заказов с бонусами';
$_['text_recent_transactions']  = 'Последние начисления';
$_['text_all_categories']       = 'Все категории (по умолчанию)';

// Registration Widget Defaults
$_['text_register_widget_heading_default'] = 'Станьте участником программы лояльности!';
$_['text_register_widget_description_default'] = 'Регистрируйтесь и получайте бонусные баллы за каждую покупку!';
$_['text_register_button_default'] = 'Зарегистрироваться';
$_['text_yes'] = 'Да';
$_['text_no'] = 'Нет';

// Descriptions (for database records)
$_['text_return_deduction']     = 'Списание за возврат #%s';

// Error
$_['error_permission']          = 'У вас нет прав для изменения этого модуля!';
$_['error_customer_group']      = 'Необходимо выбрать группу покупателей!';
