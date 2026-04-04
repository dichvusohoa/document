1. Tính ra layout (dạng file_path)
Request + UserInfo => layout có phụ thuộc  DeviceType không
Request + UserInfo => layout có phụ thuộc  ScreenResolution không

Request
UserInfo                                
DeviceType (null nếu không cần)         =====> 4 yếu tố này map ra layout_path
ScreenResolution (null nếu không cần) 

2. Trong HtmlFragmentDescription Tính ra fragments ( của layout thôi, chưa có data)

Request
UserInfo        ===> fragement là array của các phần tử dạng
layoutPath

type: css, script, embed_fragment_layout, link_fragment_layout


path_fragment( chỉ có giá trị khi type = link_fragment_layout)


