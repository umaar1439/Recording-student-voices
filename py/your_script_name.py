import re
import requests
from bs4 import BeautifulSoup
import pandas as pd

def scrape_quran():
    quran_data = []
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }

    # المرور التلقائي والمستمر على جميع سور القرآن من 1 إلى 114
    for surah_num in range(1, 115):
        url = f"https://surahquran.com/{surah_num}.html"
        print(f"جاري معالجة السورة رقم {surah_num}: {url}")
        
        try:
            response = requests.get(url, headers=headers)
            response.encoding = 'utf-8' # دعم اللغة العربية
            
            if response.status_code != 200:
                print(f"فشل الاتصال بالصفحة رقم {surah_num}, رمز الحالة: {response.status_code}")
                continue
            
            soup = BeautifulSoup(response.text, 'html.parser')
            
            # 1. استخراج اسم السورة
            h2_tag = soup.find('h2')
            surah_name = ""
            if h2_tag:
                text = h2_tag.get_text()
                if "سورة" in text:
                    surah_name = text.split('|')[0].strip()
                    surah_name = surah_name.replace("مكتوبة كاملة بالتشكيل", "").strip()
            
            if not surah_name:
                surah_name = f"سورة {surah_num}"

            # 2. استخراج الآيات من خلال وسوم <label>
            labels = soup.find_all('label')
            
            for label in labels:
                verse_num_str = label.get_text().strip("()")
                if not verse_num_str.isdigit():
                    continue
                
                verse_num = int(verse_num_str)
                
                # تجميع نص الآية من العناصر السابقة للـ label
                verse_parts = []
                curr = label.previous_sibling
                
                while curr:
                    if curr.name == 'label' or curr.name == 'h2' or (curr.name == 'a' and curr.get('id') == 'reading'):
                        break
                    
                    if isinstance(curr, str):
                        verse_parts.insert(0, curr.strip())
                    elif curr.name in ['b', 'span', 'font', 'i', 'strong']:
                        verse_parts.insert(0, curr.get_text().strip())
                    elif curr.name == 'br':
                        verse_parts.insert(0, ' ')
                    
                    curr = curr.previous_sibling
                
                full_verse_text = " ".join(" ".join(verse_parts).split())
                
                # تنسيق المعرف بالشكل المطلوب (مثال: 001003)
                formatted_id = f"{surah_num:03d}{verse_num:03d}"
                
                quran_data.append({
                    "معرف الآية (ID)": formatted_id,
                    "اسم السورة": surah_name,
                    "رقم السورة": surah_num,
                    "رقم الآية": verse_num,
                    "نص الآية": full_verse_text
                })
                
        except Exception as e:
            print(f"حدث خطأ أثناء معالجة السورة {surah_num}: {e}")

    # 3. حفظ البيانات كاملة في ملف Excel
    df = pd.DataFrame(quran_data)
    output_filename = "Quran_Full_Data.xlsx"
    
    with pd.ExcelWriter(output_filename, engine='openpyxl') as writer:
        df.to_excel(writer, index=False, sheet_name="القرآن الكريم كامل")
        
    print(f"تم الانتهاء بنجاح! تم حفظ القرآن كاملاً في الملف: {output_filename} (إجمالي الآيات: {len(df)})")

if __name__ == "__main__":
    scrape_quran()
