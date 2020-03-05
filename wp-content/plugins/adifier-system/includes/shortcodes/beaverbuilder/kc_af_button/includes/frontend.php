<?php
$unique_id = 'btn_'.uniqid();
?>
<a href="<?php echo $settings->link ?>" class="<?php echo $unique_id; ?> <?php echo $settings->class ?>">
    <?php echo $settings->text ?>
</a>
<style scoped>
    .<?php echo $unique_id ?>,
    .<?php echo $unique_id ?>:active
    .<?php echo $unique_id ?>:focus{
        <?php echo !empty( $settings->border_width ) ? 'border: '.$settings->border_width.' solid #'.$settings->border_color .';' : '' ?>
        <?php echo !empty( $settings->border_radius ) ? 'border-radius: '. $settings->border_radius.';' : '' ?>
        <?php echo !empty( $settings->font_color ) ? 'color: #'.$settings->font_color .';' : '' ?>
        <?php echo !empty( $settings->bg_color ) ? 'background: #'.$settings->bg_color .';' : '' ?>
        <?php echo !empty( $settings->padding ) ? 'padding: '.$settings->padding.';' : '' ?>
    }

    .<?php echo $unique_id ?>:hover{
        <?php echo !empty( $settings->border_color_hvr ) ? 'border-color: #'.$settings->border_color_hvr.';' : '' ?>
        <?php echo !empty( $settings->font_color_hvr ) ? 'color: #'.$settings->font_color_hvr.';' : '' ?>
        <?php echo !empty( $settings->bg_color_hvr ) ? 'background: #'.$settings->bg_color_hvr.';' : '' ?>
    }    
</style>