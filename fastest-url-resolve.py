import concurrent.futures
import multiprocessing
import requests
import socket
from urllib.parse import urlparse
from concurrent.futures import ThreadPoolExecutor, ProcessPoolExecutor
import time
import sys
import warnings
import threading
from requests.adapters import HTTPAdapter
from urllib3.exceptions import InsecureRequestWarning
from functools import partial
import queue

#usage - python3 fastest-url-resolve.py urls.txt | tee url-resolved.txt

# Suppress SSL warnings
warnings.filterwarnings('ignore', category=InsecureRequestWarning)

def create_session():
    session = requests.Session()
    adapter = HTTPAdapter(
        pool_connections=1000,
        pool_maxsize=1000,
        max_retries=0,
        pool_block=False
    )
    session.mount('http://', adapter)
    session.mount('https://', adapter)
    
    session.headers.update({
        'Connection': 'keep-alive',
        'User-Agent': 'Mozilla/5.0',
        'Accept': '*/*'
    })
    return session

def check_url(url: str, timeout: float = 1.5) -> list:
    if not url.strip():
        return []
        
    domain = url.strip()
    if not domain.startswith(('http://', 'https://')):
        domain = domain.split('/')[0]
        
    working_urls = []
    session = create_session()
    
    try:
        socket.setdefaulttimeout(0.5)
        try:
            socket.gethostbyname(domain)
        except:
            return []

        def check_protocol(protocol):
            try:
                response = session.get(
                    f"{protocol}://{domain}",
                    timeout=timeout,
                    verify=False,
                    allow_redirects=False,
                    stream=True
                )
                response.raw.read = partial(response.raw.read, decode_content=False)
                if response.status_code < 500:
                    return f"{protocol}://{domain}"
            except:
                pass
            return None

        with ThreadPoolExecutor(max_workers=2) as protocol_executor:
            futures = {
                protocol_executor.submit(check_protocol, protocol): protocol 
                for protocol in ['http', 'https']
            }
            
            for future in concurrent.futures.as_completed(futures):
                result = future.result()
                if result:
                    working_urls.append(result)

        return working_urls

    except Exception:
        return []
    finally:
        session.close()

def process_chunk(urls: list) -> list:
    results = []
    with ThreadPoolExecutor(max_workers=130) as executor:
        futures = [executor.submit(check_url, url) for url in urls]
        for future in concurrent.futures.as_completed(futures):
            results.extend(future.result())
    return results

def process_urls(urls: list) -> None:
    cpu_count = multiprocessing.cpu_count()
    optimal_chunk_size = max(min(len(urls) // (cpu_count * 8), 50), 1)
    chunks = [urls[i:i + optimal_chunk_size] for i in range(0, len(urls), optimal_chunk_size)]
    
    process_count = min(cpu_count * 2, len(chunks))
    
    with ProcessPoolExecutor(max_workers=process_count) as process_executor:
        futures = [process_executor.submit(process_chunk, chunk) for chunk in chunks]
        
        for future in concurrent.futures.as_completed(futures):
            for url in future.result():
                print(url, flush=True)

def main():
    if len(sys.argv) != 2:
        print("Usage: python script.py urls.txt")
        return

    try:
        with open(sys.argv[1], 'r') as f:
            urls = [line.strip() for line in f if line.strip()]
    except Exception as e:
        print(f"Error reading file: {e}")
        return

    # Maximize system limits for non-Windows systems
    if sys.platform != 'win32':
        import resource
        resource.setrlimit(resource.RLIMIT_NOFILE, (999999, 999999))

    # Set socket level optimizations
    socket.setdefaulttimeout(1.5)

    process_urls(urls)

if __name__ == "__main__":
    try:
        multiprocessing.freeze_support()
        main()
    except KeyboardInterrupt:
        sys.exit(1)
