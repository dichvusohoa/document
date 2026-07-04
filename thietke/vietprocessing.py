#!/usr/bin/python
# -*- coding: utf8 -*-

char = u"àảãáạăằẳẵắặâầẩẫấậòỏõóọôồổỗốộơờởỡớợèẻẽéẹêềểễếệùủũúụưừửữứựìỉĩíịỳỷỹýỵđ" #kí tự thuần việt
kdau = u"aAăĂâÂbBcCdDđĐeEêÊfFgGhHiIjJkKlLmMnNoOôÔơƠpPqQrRsStTuUưƯvVwWxXyYzZ"
cdau = u"aàảãáạăằẳẵắặâầẩẫấậeèẻẽéẹêềểễếệiìỉĩíịoòỏõóọôồổỗốộơờởỡớợuùủũúụưừửữứựyỳỷỹýỵAÀẢÃÁẠĂẰẲẴẮẶÂẦẨẪẤẬEÈẺẼÉẸÊỀỂỄẾỆIÌỈĨÍỊOÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢUÙỦŨÚỤƯỪỬỮỨỰYỲỶỸÝỴ"

def BoThanhDieu(a): #hoạt động chính xác
    #loại bỏ các dấu sắc, huyền, hỏi, ngã, nặng khỏi kí tự, ví dụ ắ -> ă
    #không hoạt động với kí tự "đ" vì theo nguyên tắc kí tự này không có thanh điệu đi cùng
    Cdau = u"aàảãáạăằẳẵắặâầẩẫấậeèẻẽéẹêềểễếệiìỉĩíịoòỏõóọôồổỗốộơờởỡớợuùủũúụưừửữứựyỳỷỹýỵAÀẢÃÁẠĂẰẲẴẮẶÂẦẨẪẤẬEÈẺẼÉẸÊỀỂỄẾỆIÌỈĨÍỊOÒỎÕÓỌÔỒỔỖỐỘƠỜỞỠỚỢUÙỦŨÚỤƯỪỬỮỨỰYỲỶỸÝỴ"
    a+=u""
    i = Cdau.find(a)
    if i==-1 : return a #ko tìm thấy a trong chuỗi Cdau nên trả lại chính a
    return Cdau[i-i%6]

def BoDau(a): #hoạt động chính xác
    #convert kí tự tiếng Việt sang kí tự latin. 
    Char = u"àảãáạăằẳẵắặâầẩẫấậòỏõóọôồổỗốộơờởỡớợèẻẽéẹêềểễếệùủũúụưừửữứựìỉĩíịỳỷỹýỵđ"
    kqua = u"aAoOeEuUiIyYdD"
    a+=u""
    lower = False
    b = a.lower()
    #print a, b==a 
    if (a==b): lower = True
    else : lower = False
    i = Char.find(b)
    if i==-1 :return a
    if i<=16 :return kqua[0+1-lower]    
    if i<=33 :return kqua[2+1-lower]    
    if i<=44 :return kqua[4+1-lower]    
    if i<=55 :return kqua[6+1-lower]    
    if i<=60 :return kqua[8+1-lower]
    if i<=65 :return kqua[10+1-lower]    
    if i<=66 :return kqua[12+1-lower]    

def SoSanh(a,b): #tam thoi hoan thanh, chua su dung dc voi cac ki tu ko thuoc khai bao toanbo
    #so sánh 2 chuỗi a, b. 
    #Nếu a nhỏ hơn(đứng trước theo thứ tự từ điển) thì trả lại giá trị 1, 
    #nếu b nhỏ hơn thì trả lại -1, 
    #nếu 2 chuỗi giống nhau thì trả lại 0
    toanbo = u"_0123456789aAàÀảẢãÃáÁạẠăĂằẰẳẲẵẴắẮặẶâÂầẦẩẨẫẪấẤậẬbBcCdDđĐeEèÈẻẺẽẼéÉẹẸêÊềỀểỂễỄếẾệỆfFgGhHiIìÌỉỈĩĨíÍịỊjJkKlLmMnNoOòÒỏỎõÕóÓọỌôÔồỒổỔỗỖốỐộỘơƠờỜởỞỡỠớỚợỢpPqQrRsStTuUùÙủỦũŨúÚụỤưƯừỪửỬữỮứỨựỰvVwWxXyYỳỲỷỶỹỸýÝỵỴzZ"
    a += u""
    b += u""
    aa = ""
    bb = ""
    for i in range(0,len(a)): 
        k = toanbo.find(a[i])
        if k in range(256): aa += chr(k)
    for i in range(0,len(b)):
        k = toanbo.find(b[i])
        if k in range(256): bb += chr(k)
    
    if aa==bb : return 0
    if aa<bb : return 1
    else : return -1