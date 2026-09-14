@props(['look' => 'daily'])

<svg {{ $attributes }} viewBox="0 0 400 440" fill="none" aria-hidden="true">
    <ellipse cx="200" cy="409" rx="142" ry="13" fill="#17343a" opacity=".08"/>
    @if ($look === 'office')
        <path d="m146 47-55 26-31 118 45 12 23-75-6 149h157l-7-149 25 75 43-12-31-118-54-26Z" fill="#6f8d94"/><path d="m174 45 26 64 28-64" fill="#f5f3eb"/><path d="m172 45-28 58 41 27-14 73 30 73 29-73-14-73 39-27-27-58" stroke="#466570" stroke-width="3"/><path d="M200 110v164M139 224h31m62 0h31" stroke="#466570" stroke-width="3"/><circle cx="208" cy="188" r="3" fill="#d7ddd5"/>
        <path d="M138 294h126l12 112h-55l-20-85-20 85h-55Z" fill="#17343a"/>
    @elseif ($look === 'weekend')
        <path d="m144 62-56 32-36 61 45 27 33-44-5 115h151l-5-115 33 44 44-27-35-61-56-32a59 59 0 0 1-113 0Z" fill="#e49c89"/><path d="M170 66a34 34 0 0 0 61 0M132 237h135" stroke="#bd776b" stroke-width="3"/>
        <path d="M132 274h138l19 124h-67l-22-92-22 92h-64Z" fill="#90adb9"/><path d="M134 293h134M200 281v29M144 298l-9 17m119-17 10 17" stroke="#65838f" stroke-width="3"/>
        <path d="m300 287 31-4 9 35-41 5Z" fill="#f4eee0"/><path d="M303 288c-8-36 29-39 29-5" stroke="#f4eee0" stroke-width="7"/>
    @else
        <path d="m143 55-53 27-38 78 43 21 37-57-6 142h148l-6-142 37 57 44-21-39-78-52-27Z" fill="#f2eee3"/><path d="m170 55 30 41 29-41M170 55l-14 45 25-5 19 22 19-22 24 5-14-45M200 98v164" stroke="#cec9ba" stroke-width="3"/><path d="M225 142h31v35h-31Z" stroke="#cec9ba" stroke-width="2"/><circle cx="205" cy="141" r="2.5" fill="#9da696"/><circle cx="205" cy="178" r="2.5" fill="#9da696"/><circle cx="205" cy="215" r="2.5" fill="#9da696"/>
        <path d="M135 287h131l12 117h-55l-23-87-23 87h-56Z" fill="#5b7970"/><path d="M136 302h129M199 292v26" stroke="#3f5e56" stroke-width="3"/>
        <rect x="294" y="279" width="54" height="66" rx="10" fill="#e49c89"/><path d="M305 281v-13a16 16 0 0 1 32 0v13" stroke="#bd776b" stroke-width="6"/>
    @endif
</svg>
