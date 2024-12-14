import asyncio
import sys
import time
import socket
import warnings
import multiprocessing
from datetime import datetime

#written by chiragartani, it is fastest with perfect accuracy will consume minium RAM, CPU, accurate results.
#usage - python fastest-port-scan-with-accuracy.py 80,81,82,83,84,88,161,443,3000,3001,4000,4433,4443,4848,4849,5000,5001,5555,5556,5557,6000,6001,6443,6660,6661,6662,6663,6664,7000,7001,7002,7003,7004,7005,7006,7007,8000,8001,8003,8004,8005,8008,8009,8040,8042,8044,8046,8048,8050,8060,8061,8062,8070,8071,8072,8080,8081,8082,8083,8084,8085,8086,8087,8088,8089,8090,8091,8092,8093,8094,8095,8096,8097,8098,8099,8143,8161,8162,8180,8181,8280,8281,8443,8530,8531,8800,8877,8878,8879,8880,8881,8882,8883,8888,9000,9001,9002,9003,9004,9005,9090,9091,9999,10000,10001,10002,10003,10004,54,50,52
warnings.filterwarnings("ignore")

class FastScanner:
    def __init__(self, timeout=0.5):  # Reduced timeout for speed
        self.timeout = timeout
        self.scanned = multiprocessing.Value('i', 0)
        self.found = multiprocessing.Value('i', 0)
        self.start_time = time.time()

    def get_stats(self):
        elapsed = time.time() - self.start_time
        with self.scanned.get_lock():
            total = self.scanned.value
        with self.found.get_lock():
            found = self.found.value
        speed = total / elapsed if elapsed > 0 else 0
        return f"{total} ports @ {speed:.0f}/s | Found: {found}"

    async def check_port(self, ip, port):
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(self.timeout)
        try:
            loop = asyncio.get_event_loop()
            await loop.run_in_executor(None, sock.connect, (ip, port))
            with self.found.get_lock():
                self.found.value += 1
            return port, True
        except:
            return port, False
        finally:
            with self.scanned.get_lock():
                self.scanned.value += 1
            sock.close()

    async def scan_batch(self, ip, ports):
        tasks = [self.check_port(ip, port) for port in ports]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        return [port for port, status in results if status]

def worker(ip_chunk, ports, output_file, worker_id):
    async def run():
        scanner = FastScanner()
        batch_size = 500  # Increased batch size
        
        for ip in ip_chunk:
            open_ports = []
            for i in range(0, len(ports), batch_size):
                batch = ports[i:i + batch_size]
                open_batch = await scanner.scan_batch(ip, batch)
                if open_batch:
                    open_ports.extend(open_batch)
                    with open(output_file, 'a') as f:
                        for port in open_batch:
                            result = f"{ip}:{port}\n"
                            f.write(result)
                            print(f"\n[+] {result}", end='')
                
                print(f"\r[{worker_id}] {ip} | {scanner.get_stats()}", end='', flush=True)

    asyncio.run(run())

def main():
    if len(sys.argv) != 2:
        print("Usage: python script.py <ports>")
        return

    # Parse unique ports
    ports = sorted(set(int(p) for p in sys.argv[1].split(',')))
    
    # Read IPs
    with open('no-waf-ips.txt') as f:
        ips = [ip.strip() for ip in f.readlines()]

    # Setup output
    output_file = 'scan-results/port-scan-results.txt'
    open(output_file, 'w').close()

    # Calculate workers based on CPU cores
    workers = max(1, multiprocessing.cpu_count() - 1)
    chunk_size = max(1, len(ips) // workers)
    chunks = [ips[i:i + chunk_size] for i in range(0, len(ips), chunk_size)]

    print(f"Starting scan: {len(ips)} IPs × {len(ports)} ports using {workers} workers")

    # Launch workers
    processes = []
    for i, chunk in enumerate(chunks):
        p = multiprocessing.Process(target=worker, args=(chunk, ports, output_file, i+1))
        p.start()
        processes.append(p)

    try:
        for p in processes:
            p.join()
    except KeyboardInterrupt:
        print("\nStopping...")
        for p in processes:
            p.terminate()

if __name__ == '__main__':
    multiprocessing.freeze_support()
    main()
