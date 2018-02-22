<div>
    <?php foreach ($$targetObject as $row):?>                                              
        <div class='square-action'>
		
		<?php if(
			$row['sku_missin_inside_product'] != 'sku_missin_inside_product' and 
			$row['sku_misin_squ_woo_pro_variable'] != 'sku_misin_squ_woo_pro_variable'
		){ 
		?>
			
           
			    <input name='woo_square_product' type='checkbox' <?php 
				echo "value='".$row['checkbox_val']."'"; 	echo " checked "; 
				?> />
		   
	
		<?php } ?>
		
            <?php if(!empty($row['woo_id'])){ ?>
				<a target='_blank' href='<?php echo admin_url(); ?>post.php?post=<?php echo $row['woo_id']; ?>&action=edit'><?php echo $row['name']; ?></a>
            <?php } else { ?>
				<?php echo $row['name']; ?>
            <?php } ?>
              


        </div>                        
    <?php endforeach; ?>
</div>