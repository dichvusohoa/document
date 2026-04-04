Quy Ước Đặt Tên Constraint Cho Dự Án
📌 Nguyên tắc chung:
Dùng tiền tố để xác định loại constraint:

1  pk__ cho Primary Key    : pk__<table> : KHÔNG LÀM ĐƯỢC maria không cho sửa

fk__ cho Foreign Key: fk__<child_table>__<parent_table>__<foreign_column>
Ví dụ fk__auth_token__user__user_id

2  uk__ cho Unique Key: uk__<table>__<column> hoặc uk__<table>__<column1>_<column2>

uk__auth_token__token

3 ck__ cho Check Constraint ck__<table>__<column>__<diễn_giải>

Ví dụ 

4 idx__ cho Index
idx__<table>__<column>


