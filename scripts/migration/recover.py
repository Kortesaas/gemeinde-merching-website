#!/usr/bin/env python3
"""Recover only inventoried public NextGEN originals; immutable backups stay untouched."""
import csv
import hashlib
import json
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from urllib.request import Request, build_opener
from urllib.robotparser import RobotFileParser

from crawl import Rate, SafeRedirect, normalize

ROOT = Path(__file__).resolve().parents[2]


def main():
    inventory = ROOT / 'docs/migration/inventory'
    recovery = ROOT / 'migration-source/recovery'
    recovery.mkdir(parents=True, exist_ok=True)
    items = list(csv.DictReader((inventory / 'gallery-items.csv').open()))
    relationships = {r['picture_id']: r['gallery_id'] for r in items}
    missing = [r for r in csv.DictReader((inventory / 'missing-files.csv').open()) if r['reference'].startswith('ngg:')]
    rate = Rate(.6)
    robot = RobotFileParser()
    with build_opener(SafeRedirect()).open(Request('https://www.gemeinde-merching.de/robots.txt', headers={'User-Agent': 'MerchingMigrationInventory/1.0'}), timeout=25) as response:
        robot.parse(response.read().decode().splitlines())

    def fetch(row):
        url = normalize(row['source_url'])
        result = {'source_id': row['reference'], 'original_url': url, 'path': row['path'], 'gallery_id': relationships[row['reference'].split(':')[1]], 'sha256': '', 'bytes': 0, 'status': 'missing source'}
        target = recovery / row['path']
        try:
            if not url or not robot.can_fetch('MerchingMigrationInventory', url):
                raise ValueError('Public URL excluded')
            if not target.exists():
                rate.acquire()
                with build_opener(SafeRedirect()).open(Request(url, headers={'User-Agent': 'MerchingMigrationInventory/1.0 (public original recovery)'}), timeout=40) as response:
                    if response.status != 200 or not response.headers.get('Content-Type', '').startswith('image/'):
                        raise ValueError('Not a complete public image')
                    data = response.read(32 * 1024 * 1024 + 1)
                    if len(data) > 32 * 1024 * 1024 or not data.startswith((b'\xff\xd8\xff', b'\x89PNG', b'GIF8', b'RIFF')):
                        raise ValueError('Invalid/oversized image')
                    if response.headers.get('Content-Length') and len(data) != int(response.headers['Content-Length']):
                        raise ValueError('Incomplete original')
                    target.parent.mkdir(parents=True, exist_ok=True)
                    target.write_bytes(data)
            data = target.read_bytes()
            result.update(sha256=hashlib.sha256(data).hexdigest(), bytes=len(data), status='recovered')
        except Exception as error:
            result['status'] = 'missing source: ' + type(error).__name__
        return result

    with ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(fetch, missing))
    (recovery / 'manifest.json').write_text(json.dumps(results, indent=2))
    report = ROOT / 'docs/migration/local'
    report.mkdir(parents=True, exist_ok=True)
    with (report / 'recovered-files.csv').open('w', newline='') as handle:
        writer = csv.DictWriter(handle, fieldnames=list(results[0]), lineterminator='\n')
        writer.writeheader()
        writer.writerows(results)
    print(json.dumps({'originals': len(results), 'recovered': sum(r['status'] == 'recovered' for r in results)}))
    if any(r['status'] != 'recovered' for r in results):
        raise SystemExit(1)


if __name__ == '__main__':
    main()
