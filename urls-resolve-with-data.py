import concurrent.futures
import multiprocessing
import requests
import socket
from urllib.parse import urlparse
from concurrent.futures import ThreadPoolExecutor, ProcessPoolExecutor
import time
from typing import List, Dict, Set
import warnings
import sys
from dataclasses import dataclass
from requests.packages.urllib3.exceptions import InsecureRequestWarning


#usage - python3 urls-resolve-with-data.py urls.txt
# Suppress only the specific warning
warnings.filterwarnings('ignore', category=InsecureRequestWarning)

@dataclass
class URLResult:
    url: str
    ip: str = None
    status: str = None
    protocol: str = None
    error: str = None

class URLResolver:
    def __init__(self, timeout: int = 5, max_workers: int = None, processes: int = None):
        self.timeout = timeout
        self.max_workers = max_workers or (multiprocessing.cpu_count() * 2)
        self.processes = processes or multiprocessing.cpu_count()
        self.session = self._create_session()

    def _create_session(self) -> requests.Session:
        session = requests.Session()
        adapter = requests.adapters.HTTPAdapter(
            pool_connections=100,
            pool_maxsize=100,
            max_retries=0,
            pool_block=False
        )
        session.mount('http://', adapter)
        session.mount('https://', adapter)
        return session

    def resolve_single_url(self, url: str) -> URLResult:
        result = URLResult(url=url)
        
        try:
            # Clean up URL
            if not url.startswith(('http://', 'https://')):
                url = f"http://{url}"
            
            parsed = urlparse(url)
            domain = parsed.netloc or parsed.path
            
            # DNS Resolution
            try:
                ip = socket.gethostbyname(domain)
                result.ip = ip
            except socket.gaierror:
                result.error = "DNS resolution failed"
                return result

            # Try HTTP
            try:
                response = self.session.get(f"http://{domain}", 
                                         timeout=self.timeout, 
                                         verify=False, 
                                         allow_redirects=False)
                result.status = str(response.status_code)
                result.protocol = "http"
                return result
            except requests.exceptions.RequestException:
                # Try HTTPS if HTTP fails
                try:
                    response = self.session.get(f"https://{domain}", 
                                             timeout=self.timeout, 
                                             verify=False, 
                                             allow_redirects=False)
                    result.status = str(response.status_code)
                    result.protocol = "https"
                    return result
                except requests.exceptions.RequestException as e:
                    result.error = str(e)
                    return result
                
        except Exception as e:
            result.error = str(e)
            
        return result

    def process_chunk(self, urls: List[str]) -> List[URLResult]:
        with ThreadPoolExecutor(max_workers=self.max_workers) as executor:
            return list(executor.map(self.resolve_single_url, urls))

    def resolve_urls(self, urls: List[str]) -> List[URLResult]:
        # Split URLs into chunks for processing
        chunk_size = len(urls) // (self.processes * 4) + 1
        chunks = [urls[i:i + chunk_size] for i in range(0, len(urls), chunk_size)]
        
        # Process chunks in parallel
        with ProcessPoolExecutor(max_workers=self.processes) as executor:
            results = []
            for chunk_result in executor.map(self.process_chunk, chunks):
                results.extend(chunk_result)
        
        return results

def main():
    if len(sys.argv) != 2:
        print("Usage: python script.py urls.txt")
        return

    # Read URLs from file
    with open(sys.argv[1], 'r') as f:
        urls = [line.strip() for line in f if line.strip()]

    start_time = time.time()
    
    # Initialize resolver
    resolver = URLResolver(timeout=3)
    
    # Process URLs
    results = resolver.resolve_urls(urls)
    
    # Print results
    print("\nResults:")
    print("-" * 80)
    for result in results:
        status = f"[{result.status}]" if result.status else ""
        protocol = f"[{result.protocol}]" if result.protocol else ""
        ip = f"[{result.ip}]" if result.ip else ""
        error = f"[ERROR: {result.error}]" if result.error else ""
        
        print(f"{result.url} {status} {protocol} {ip} {error}".strip())
    
    print("-" * 80)
    print(f"Total time: {time.time() - start_time:.2f} seconds")
    print(f"Processed {len(urls)} URLs")

if __name__ == "__main__":
    main()
