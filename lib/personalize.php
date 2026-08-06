<?php
declare(strict_types=1);

/* ============================================================
   CÁ NHÂN HOÁ — danh sách chủ đề/loại địa điểm hợp lệ +
   máy sinh chip gợi ý cho AI Gia sư, dựa trên sở thích, chủ đề
   yếu (điểm game thấp) và chủ đề vừa hỏi gần đây.
   ============================================================ */

function pref_topic_codes(): array {
    return ['den-tin-hieu','bien-bao','mu-bao-hiem','qua-duong','xe-dap','ngoi-xe','uu-tien'];
}
function pref_place_types(): array {
    return ['bao-tang','cong-vien','vui-choi','thien-nhien'];
}

/* Ngân hàng câu gợi ý: mỗi topic 2 phiên bản theo khối lớp */
function chip_bank(): array {
    return [
        'den-tin-hieu' => ['tieu-hoc' => 'Đèn vàng thì con phải làm gì? 🚦', 'thcs' => 'Vượt đèn vàng có bị phạt không? 🚦'],
        'bien-bao'     => ['tieu-hoc' => 'Biển tròn viền đỏ nghĩa là gì? 🚫', 'thcs' => 'Phân biệt biển cấm và biển hiệu lệnh? 🚫'],
        'mu-bao-hiem'  => ['tieu-hoc' => 'Đội mũ bảo hiểm đúng cách? ⛑️', 'thcs' => 'Chọn mũ bảo hiểm đạt chuẩn thế nào? ⛑️'],
        'qua-duong'    => ['tieu-hoc' => 'Qua đường an toàn làm sao? 🚸', 'thcs' => 'Quy tắc nhìn trái–phải–trái khi sang đường? 🚸'],
        'xe-dap'       => ['tieu-hoc' => 'Đi xe đạp cần nhớ gì? 🚲', 'thcs' => 'Xe đạp điện có bắt buộc đội mũ không? 🚲'],
        'ngoi-xe'      => ['tieu-hoc' => 'Ngồi sau xe máy thế nào cho an toàn? 🛵', 'thcs' => 'Vì sao phải thắt dây an toàn trên ô tô? 🚗'],
        'uu-tien'      => ['tieu-hoc' => 'Gặp xe cứu thương thì làm gì? 🚑', 'thcs' => 'Những xe nào được quyền ưu tiên? 🚑'],
    ];
}

function build_suggested_chips(array $favTopics, array $weakTopics, array $recentTopics,
                               string $gradeBand, int $limit = 6): array {
    $band = $gradeBand === 'thcs' ? 'thcs' : 'tieu-hoc';
    $bank = chip_bank();
    $order = [];
    foreach ([$weakTopics, $favTopics, $recentTopics, array_keys($bank)] as $group) {
        foreach ($group as $t) {
            if (isset($bank[$t]) && !in_array($t, $order, true)) $order[] = $t;
        }
    }
    $chips = [];
    foreach (array_slice($order, 0, $limit) as $t) {
        $chips[] = ['topic' => $t, 'text' => $bank[$t][$band]];
    }
    return $chips;
}
