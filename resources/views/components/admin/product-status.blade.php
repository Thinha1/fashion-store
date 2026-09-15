@props(['value'])
<x-admin.status :value="$value === 'active'"
    :active-label="\App\Models\Product::STATUS_LABELS['active']"
    :inactive-label="\App\Models\Product::STATUS_LABELS['archived']" />
