<?php declare(strict_types=1); ?>
<?php
$editable = array_intersect_key($record, array_flip(['id', 'name', 'slug', 'description', 'colour', 'icon', 'display_order', 'is_active']));
$encoded = htmlspecialchars(json_encode($editable, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
?>
<button type="button" class="btn btn-sm btn-outline-secondary" data-scope-edit="<?= $type ?>" data-record="<?= $encoded ?>">Edit</button>
<?php if ($type === 'sub-teams' || $isGlobalAdmin): ?>
    <button type="button" class="btn btn-sm btn-outline-danger" data-scope-delete="<?= $type ?>" data-record="<?= $encoded ?>">Delete</button>
<?php endif; ?>
