<?php
/**
 * תבנית דף ייבוא התמחויות וטיפולים
 *
 * @package ClinicQueue
 * @subpackage Admin
 *
 * @var string $message הודעת סטטוס (1=הצלחה, 0=כישלון)
 * @var string $url    כתובת עם nonce להרצת הייבוא
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html__('ייבוא התמחויות וטיפולים', 'clinic-queue-management'); ?></h1>
    <?php if ($message === '1') : ?>
        <div class="notice notice-success"><p><?php echo esc_html__('הייבוא הושלם בהצלחה.', 'clinic-queue-management'); ?></p></div>
    <?php elseif ($message === '0') : ?>
        <div class="notice notice-error"><p><?php echo esc_html__('הייבוא נכשל. בדוק שקובץ core/out-specialties.json קיים.', 'clinic-queue-management'); ?></p></div>
    <?php endif; ?>
    <p><?php echo esc_html__('ייבוא יוצר את כל ההתמחויות וסוגי הטיפולים מקובץ ה-JSON. הרץ רק כשאתה מעדכן את הקובץ.', 'clinic-queue-management'); ?></p>
    <p><a href="<?php echo esc_url($url); ?>" class="button button-primary"><?php echo esc_html__('הרץ ייבוא', 'clinic-queue-management'); ?></a></p>
</div>
