<div>
    <?php foreach ($$targetObject as $row):?>                                              
        <div class='square-action'>
            <input name='woo_square_category' type='checkbox' value='<?php echo $row['checkbox_val']; ?>' checked />

            <?php if ( !empty($row['woo_id'])):?>
                <a target='_blank' href='<?php echo admin_url(); ?>edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=<?php echo $row['woo_id']; ?>&post_type=product'><?php echo $row['name']; ?></a>
            <?php else:?>
                <?php echo $row['name']; ?>
            <?php endif;?>

        </div>                        
    <?php endforeach; ?>
</div>