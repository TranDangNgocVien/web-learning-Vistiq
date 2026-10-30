<?php

return [
    'groups' => [
        'access' => [
            'label' => 'Truy cập hệ thống',
            'description' => 'Quyền vào khu vực quản trị.',
            'permissions' => [
                'admin.access' => [
                    'name' => 'Truy cập Admin',
                    'description' => 'Mở khu vực Admin Studio.',
                ],
            ],
        ],
        'content' => [
            'label' => 'Ngân hàng nội dung',
            'description' => 'Câu hỏi, tài nguyên và đề thi.',
            'permissions' => [
                'content.view' => [
                    'name' => 'Xem nội dung',
                    'description' => 'Xem câu hỏi, tài nguyên, đề thi và kho kiến thức.',
                ],
                'content.edit' => [
                    'name' => 'Biên tập nội dung',
                    'description' => 'Tạo, sửa, import và gửi nội dung đi duyệt.',
                ],
                'content.review' => [
                    'name' => 'Duyệt nội dung',
                    'description' => 'Duyệt hoặc trả lại câu hỏi và đề thi.',
                ],
                'content.publish' => [
                    'name' => 'Phát hành nội dung',
                    'description' => 'Phát hành, lưu trữ nội dung và thao tác quản trị kết quả đặc quyền.',
                ],
                'exams.edit' => [
                    'name' => 'Cấu hình đề thi',
                    'description' => 'Thay đổi cấu trúc và quy tắc tạo đề.',
                ],
            ],
        ],
        'assessment' => [
            'label' => 'Kết quả và lượt thi',
            'description' => 'Theo dõi, chấm điểm và xử lý báo cáo.',
            'permissions' => [
                'results.view' => [
                    'name' => 'Xem kết quả',
                    'description' => 'Xem lượt thi, báo cáo, bảng xếp hạng và thống kê.',
                ],
                'results.grade' => [
                    'name' => 'Chấm bài',
                    'description' => 'Chấm câu trả lời Writing và Speaking.',
                ],
                'results.export' => [
                    'name' => 'Xuất báo cáo',
                    'description' => 'Tải báo cáo thống kê và bảng xếp hạng theo bộ lọc đang xem.',
                ],
                'reports.review' => [
                    'name' => 'Xử lý báo cáo',
                    'description' => 'Duyệt báo cáo và điều chỉnh trạng thái lượt thi.',
                ],
            ],
        ],
        'administration' => [
            'label' => 'Quản trị',
            'description' => 'Tài khoản, vai trò, cài đặt và nhật ký.',
            'permissions' => [
                'users.manage' => [
                    'name' => 'Quản lý người dùng',
                    'description' => 'Xem tài khoản và cập nhật trạng thái hoặc vai trò.',
                ],
                'roles.manage' => [
                    'name' => 'Gán quyền theo vai trò',
                    'description' => 'Gán hoặc gỡ các quyền có sẵn khỏi vai trò.',
                ],
                'settings.manage' => [
                    'name' => 'Quản lý cài đặt',
                    'description' => 'Thay đổi cài đặt vận hành và sao lưu/phục hồi.',
                ],
                'audit.view' => [
                    'name' => 'Xem nhật ký hệ thống',
                    'description' => 'Tra cứu lịch sử thao tác quản trị.',
                ],
            ],
        ],
    ],

    'default_roles' => [
        'admin' => [
            'name' => 'Admin',
            'description' => 'Quản trị viên mặc định với toàn bộ quyền trong hệ thống.',
            'permissions' => '*',
        ],
        'super-admin' => [
            'name' => 'Super Admin',
            'description' => 'Vai trò hệ thống có toàn quyền và không chỉnh sửa tại màn gán quyền.',
            'permissions' => '*',
        ],
        'content-admin' => [
            'name' => 'Content Admin',
            'description' => 'Biên tập câu hỏi và đề thi.',
            'permissions' => ['admin.access', 'content.view', 'content.edit', 'exams.edit'],
        ],
        'reviewer' => [
            'name' => 'Reviewer',
            'description' => 'Duyệt nội dung và xử lý kết quả được phân quyền.',
            'permissions' => ['admin.access', 'content.view', 'content.review', 'results.view', 'results.grade', 'reports.review'],
        ],
    ],
];
