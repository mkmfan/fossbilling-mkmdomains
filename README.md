# MKM Domains — FOSSBilling Registrar Adapter

Connect [FOSSBilling](https://github.com/FOSSBilling/FOSSBilling) to the
**MKM Domains registry API** and start selling `mkm.fan` and `oya.to`
domains with automatic registration, renewal and release.

Works with any FOSSBilling install — use it for your own store or become
a reseller with your own brand, prices and payment gateways.

## Features

- **Live availability + pricing** from the registry (tier-based: standard / 1-2-3 char)
- **Auto register** on paid order (no manual activation)
- **Auto renew** after renewal payment (1-10 years)
- **Release** when a service is deleted
- **Custom nameservers** — NS delegation mode when the customer enters NS at checkout
- **Zone-agnostic** — sells any zone enabled for your API key
  (mkm.fan, oya.to and future zones — no code change needed)

## Requirements

- FOSSBilling 0.8.x
- An MKM Domains registry API key (`mkm_live_...`) — see below
- PHP with curl

## Installation

1. Copy `Mkmdomains.php` to:
   `src/library/Registrar/Adapter/Mkmdomains.php`
2. In FOSSBilling admin go to **System → Domain Management → Registrars**
   and open **MKM Domains**:
   - **API Endpoint:** `https://mkm.fan/system/registry.php`
   - **API Key:** your `mkm_live_...` key
   - **Default Nameservers:** `ns1.mkm.fan` / `ns2.mkm.fan`
3. Add your TLDs under **Top Level Domains** (`.mkm.fan`, `.oya.to`),
   assign the MKM Domains registrar and set your own prices.
4. Done — paid orders register instantly.

## How to get an API key

Become an MKM Domains reseller:

- Website: **https://mkm.fan**
- Email: **web@mkm.fan**

You get your own API key, your chosen zones and limits. You set your own
prices and keep your margin — your customers only see your brand.

## Notes

- Premium names (1/2/3 character and premium words) are not sold through
  the module — the availability check returns a clear message for them.
- Expired domains are suspended automatically by the registry
  (site goes offline, DNS records removed).
- `deleteDomain` releases the name back to the market.

## License

MIT — see [LICENSE](LICENSE).
