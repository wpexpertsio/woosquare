<div class="wrap">
    <div class="welcome-panel">

        <form method="post">

            <?php echo __('Synchronization type:'); ?>
            <select name="log_sync_type">
                <option <?php if (is_null($sync_type)) echo "selected"; ?>                         value="any">Any</option>
                <option <?php if ($sync_type === $helper::SYNC_TYPE_MANUAL) echo "selected"; ?>    value="<?php echo $helper::SYNC_TYPE_MANUAL; ?>"> <?php echo $helper->getSyncType($helper::SYNC_TYPE_MANUAL); ?> </option>
                <option <?php if ($sync_type === $helper::SYNC_TYPE_AUTOMATIC) echo "selected"; ?> value="<?php echo $helper::SYNC_TYPE_AUTOMATIC; ?>"> <?php echo $helper->getSyncType($helper::SYNC_TYPE_AUTOMATIC); ?> </option>
            </select> 

            <?php echo __('Synchronization direction:'); ?>
            <select name="log_sync_direction">
                <option <?php if (is_null($sync_direction)) echo "selected"; ?>                                         value="any">Any</option>
                <option <?php if ($sync_direction === $helper::SYNC_DIRECTION_WOO_TO_SQUARE) echo "selected"; ?> value="<?php echo $helper::SYNC_DIRECTION_WOO_TO_SQUARE; ?>"> <?php echo $helper->getSyncDirection($helper::SYNC_DIRECTION_WOO_TO_SQUARE); ?> </option>
                <option <?php if ($sync_direction === $helper::SYNC_DIRECTION_SQUARE_TO_WOO) echo "selected"; ?> value="<?php echo $helper::SYNC_DIRECTION_SQUARE_TO_WOO; ?>"> <?php echo $helper->getSyncDirection($helper::SYNC_DIRECTION_SQUARE_TO_WOO); ?> </option>
            </select>

            <?php echo __('From:'); ?>
            <select name="log_sync_date">
                <option <?php if ($sync_date == 1) echo "selected"; ?> value="1">Last day</option>
                <option <?php if ($sync_date == 7) echo "selected"; ?> value="7">Last Week</option>
                <option <?php if ($sync_date == 30) echo "selected"; ?> value="30">Last Month</option>
                <option <?php if (is_null($sync_date)) echo "selected"; ?> value="any">All</option>
            </select>
            <input type="submit" value="Get Logs" class="button button-primary">
        </form>

        <?php $lastRows = $rows = 0;
        $lastSyncLogId = -1;
        ?>
        <?php if (empty($results)): ?>
            <div class='empty-logs-data'>
                <?php echo __("No logs found");?>
            </div>
        <?php else: ?>
            <?php foreach ($results as $result): ?>

                <?php
                if (($lastSyncLogId != $result->log_id) && ($result->log_action == $helper::ACTION_SYNC_START)):  //new sync process    
                    $lastSyncLogId = $result->log_id;                                   //START new synchronization row 
                    $lastRows = $rows; //last sync rows number
                    $rows = 0;         //new sync rows number 
                    if ($lastRows > 0):
                        ?>
                        </tbody>
                        </table></div>
                    <?php
                    endif; ?>    
                    <?php if ($lastSyncLogId!=-1): //at least 1 row displayed?>
                        </div> <!-- log data -->
                    <?php endif;?>




                    <div class="log-data">
                        <a class="collapse" href="javascript:void(0);">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                            <span class="log-title-text">
                                <?php
                                echo $helper->getSyncType($result->log_type) . " " . $helper->getSyncDirection($result->log_direction);
                                ?>
                            </span>
                            <span class="log-title-date"> 
                                <?php
                                echo $result->log_date;
                                ?>
                            </span>
                        </a>
                        <?php

                endif;
                if ($result->parent_id):                                       //CHILD data row 
                    if ($rows == 0):  //first row in table 
                        ?>
                        
                        <div class='hidden grid-div'><table class='gridtable'>
                            <thead>
                                <tr>
                                    <th><?php echo __('Synchronized Object'); ?></th>
                                    <th><?php echo __('Action');?></th>
                                    <th><?php echo __('Status');?></th>
                                    <th><?php echo __('Message');?></th>

                                </tr>
                            </thead>
                            <tbody>

                                <?php
                            endif;
                            $rows++;
                            ?>
                            <tr>
                                <td> 
                                    <?php if ($result->action != $helper::ACTION_DELETE):  //not delete ?>
                                        
                                        <?php echo $helper->getTargetType($result->target_type);?> - 
                                        <a target='_blank' href="<?php
                                        if ($result->target_type != $helper::TARGET_TYPE_CATEGORY):  //product
                                            echo admin_url() . 'post.php?post=' . $result->target_id . '&action=edit';
                                        else:               //category
                                            echo admin_url() . 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $result->target_id . '&post_type=product';
                                        endif;
                                        ?>"><?php echo $result->name; ?></a>
                                           <?php
                                       else:
                                           echo $result->name;
                                       endif;
                                       ?>
                                </td>
                                <td><?php echo $helper->getAction($result->action); ?></td>    
                                <td class='center'>
                                    <?php echo ($result->target_status==$helper::TARGET_STATUS_SUCCESS) ? 
                                        '<span class="dashicons dashicons-yes" title='.$helper->getTargetStatus($result->target_status).'></span>'
                                        :'<span class="dashicons dashicons-no-alt" title='.$helper->getTargetStatus($result->target_status).'></span>'
                                    ;?>
                                </td>
                                <td><?php echo $result->message; ?></td> 

                            </tr>

                <?php 
                else:?>
                    <div class="hidden grid-div">
                        <?php echo __('No entries found') ; ?>
                <?php endif; ?>
                
            <?php endforeach; ?>
                <?php if ($lastRows > 0): ?>
                     </tbody>
                </table></div>
                <?php endif; ?>    
                <?php if ($lastSyncLogId!=-1): //at least 1 row displayed?>
                    </div> <!-- log data -->
                <?php endif;?>
        <?php endif; ?>
  


</div>


