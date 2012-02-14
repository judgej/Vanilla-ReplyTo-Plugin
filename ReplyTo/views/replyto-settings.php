<?php if (!defined('APPLICATION')) exit();
echo $this->Form->Open();
echo $this->Form->Errors();
?>

<h1><?php echo Gdn::Translate('Reply To'); ?></h1>

<div class="Info"><?php echo Gdn::Translate('Options to apply to the ReplyTo plugin.'); ?></div>

<table class="AltRows">
    <thead>
        <tr>
            <th><?php echo Gdn::Translate('Option'); ?></th>
            <th class="Alt"><?php echo Gdn::Translate('Description'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <?php
                echo $this->Form->CheckBox(
                    'ReplyTo.Mention.Insert', 'Mention',
                    array('value' => '1', 'selected' => 'selected')
                );
                ?>
            </td>
            <td class="Alt">
                <?php echo Gdn::Translate('Mention of the user being replied to in new replies.'); ?>
            </td>
        </tr>
</table>

<?php echo $this->Form->Close('Save');
