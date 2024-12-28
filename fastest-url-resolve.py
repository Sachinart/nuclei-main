import concurrent.futures
import multiprocessing
import requests
import socket
from concurrent.futures import ThreadPoolExecutor, ProcessPoolExecutor
import time
import sys
import warnings
from requests.adapters import HTTPAdapter
from urllib3.exceptions import InsecureRequestWarning

warnings.filterwarnings('ignore', category=InsecureRequestWarning)

#usage - python3 fastest-url-resolve.py urls.txt | tee resolved-urls.txt
def check_url(url: str) -> list:
    if not url.strip():
        return []
    
    domain = url.strip()
    if not domain.startswith(('http://', 'https://')):
        domain = domain.split('/')[0]
    
    results = []
    session = requests.Session()
    adapter = HTTPAdapter(
        pool_connections=25,
        pool_maxsize=25,
        max_retries=0,
        pool_block=False
    )
    session.mount('http://', adapter)
    session.mount('https://', adapter)
    
    try:
        # Quick DNS check
        try:
            socket.setdefaulttimeout(1)
            socket.gethostbyname(domain)
        except:
            return []

        # Check HTTP
        try:
            response = session.get(
                f"http://{domain}",
                timeout=2,
                verify=False,
                allow_redirects=False
            )
            if response.status_code < 500:
                results.append(f"http://{domain}")
        except:
            pass

        # Check HTTPS
        try:
            response = session.get(
                f"https://{domain}",
                timeout=2,
                verify=False,
                allow_redirects=False
            )
            if response.status_code < 500:
                results.append(f"https://{domain}")
        except:
            pass

        return results

    except Exception:
        return []
    finally:
        session.close()

def process_urls(urls):
    with ThreadPoolExecutor(max_workers=100) as executor:
        futures = [executor.submit(check_url, url) for url in urls]
        for future in concurrent.futures.as_completed(futures):
            try:
                results = future.result()
                for url in results:
                    print(url, flush=True)
            except:
                continue

def main():
    if len(sys.argv) != 2:
        print("Usage: python script.py urls.txt", file=sys.stderr)
        return

    # Set resource limits
    if sys.platform != 'win32':
        import resource
        resource.setrlimit(resource.RLIMIT_NOFILE, (65535, 65535))

    socket.setdefaulttimeout(2)

    # Process in chunks
    chunk_size = 1000
    with open(sys.argv[1], 'r') as f:
        chunk = []
        for line in f:
            chunk.append(line.strip())
            if len(chunk) >= chunk_size:
                process_urls(chunk)
                chunk = []
        if chunk:  # Process remaining URLs
            process_urls(chunk)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        sys.exit(1)
