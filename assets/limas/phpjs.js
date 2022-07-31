function base64_decode(data) { // 266
	let b64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=',
		o1, o2, o3, h1, h2, h3, h4, bits, i = 0, ac = 0, dec = '', tmp_arr = [];
	if (!data) {
		return data;
	}
	data += '';
	do {
		h1 = b64.indexOf(data.charAt(i++));
		h2 = b64.indexOf(data.charAt(i++));
		h3 = b64.indexOf(data.charAt(i++));
		h4 = b64.indexOf(data.charAt(i++));
		bits = h1 << 18 | h2 << 12 | h3 << 6 | h4;
		o1 = bits >> 16 & 0xff;
		o2 = bits >> 8 & 0xff;
		o3 = bits & 0xff;
		if (h3 == 64) {
			tmp_arr[ac++] = String.fromCharCode(o1);
		} else if (h4 == 64) {
			tmp_arr[ac++] = String.fromCharCode(o1, o2);
		} else {
			tmp_arr[ac++] = String.fromCharCode(o1, o2, o3);
		}
	} while (i < data.length);
	dec = tmp_arr.join('');
	dec = utf8_decode(dec);
	return dec;
}

function implode(glue, pieces) { // 383
	return ((pieces instanceof Array) ? pieces.join(glue) : pieces);
}

function nl2br(str, is_xhtml) { // 506
	let breakTag = '<br />';
	if (typeof is_xhtml != 'undefined' && !is_xhtml) {
		breakTag = '<br>';
	}
	return (str + '').replace(/([^>]?)\n/g, '$1' + breakTag + '\n');
}

function sprintf() { // 622
	let regex = /%%|%(\d+\$)?([-+\'#0 ]*)(\*\d+\$|\*|\d+)?(\.(\*\d+\$|\*|\d+))?([scboxXuidfegEG])/g,
		a = arguments, i = 0, format = a[i++];
	let pad = function (str, len, chr, leftJustify) {
		if (!chr) chr = ' ';
		let padding = (str.length >= len) ? '' : Array(1 + len - str.length >>> 0).join(chr);
		return leftJustify ? str + padding : padding + str;
	};
	let justify = function (value, prefix, leftJustify, minWidth, zeroPad, customPadChar) {
		let diff = minWidth - value.length;
		if (diff > 0) {
			if (leftJustify || !zeroPad) {
				value = pad(value, minWidth, customPadChar, leftJustify);
			} else {
				value = value.slice(0, prefix.length) + pad('', diff, '0', true) + value.slice(prefix.length);
			}
		}
		return value;
	};
	let formatBaseX = function (value, base, prefix, leftJustify, minWidth, precision, zeroPad) {
		let number = value >>> 0;
		prefix = prefix && number && {'2': '0b', '8': '0', '16': '0x'}[base] || '';
		value = prefix + pad(number.toString(base), precision || 0, '0', false);
		return justify(value, prefix, leftJustify, minWidth, zeroPad);
	};
	let formatString = function (value, leftJustify, minWidth, precision, zeroPad, customPadChar) {
		if (precision != null) {
			value = value.slice(0, precision);
		}
		return justify(value, '', leftJustify, minWidth, zeroPad, customPadChar);
	};
	let doFormat = function (substring, valueIndex, flags, minWidth, _, precision, type) {
		let number, prefix, method, textTransform, value;
		if (substring === '%%') {
			return '%';
		}
		let leftJustify = false, positivePrefix = '', zeroPad = false, prefixBaseX = false, customPadChar = ' ',
			flagsl = flags.length;
		for (let j = 0; flags && j < flagsl; j++) {
			switch (flags.charAt(j)) {
				case' ':
					positivePrefix = ' ';
					break;
				case'+':
					positivePrefix = '+';
					break;
				case'-':
					leftJustify = true;
					break;
				case"'":
					customPadChar = flags.charAt(j + 1);
					break;
				case'0':
					zeroPad = true;
					break;
				case'#':
					prefixBaseX = true;
					break;
			}
		}
		if (!minWidth) {
			minWidth = 0;
		} else if (minWidth === '*') {
			minWidth = +a[i++];
		} else if (minWidth.charAt(0) === '*') {
			minWidth = +a[minWidth.slice(1, -1)];
		} else {
			minWidth = +minWidth;
		}
		if (minWidth < 0) {
			minWidth = -minWidth;
			leftJustify = true;
		}
		if (!isFinite(minWidth)) {
			throw new Error('sprintf: (minimum-)width must be finite');
		}
		if (!precision) {
			precision = 'fFeE'.indexOf(type) > -1 ? 6 : (type === 'd') ? 0 : void (0);
		} else if (precision === '*') {
			precision = +a[i++];
		} else if (precision.charAt(0) === '*') {
			precision = +a[precision.slice(1, -1)];
		} else {
			precision = +precision;
		}
		value = valueIndex ? a[valueIndex.slice(0, -1)] : a[i++];
		switch (type) {
			case's':
				return formatString(String(value), leftJustify, minWidth, precision, zeroPad, customPadChar);
			case'c':
				return formatString(String.fromCharCode(+value), leftJustify, minWidth, precision, zeroPad);
			case'b':
				return formatBaseX(value, 2, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
			case'o':
				return formatBaseX(value, 8, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
			case'x':
				return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
			case'X':
				return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad).toUpperCase();
			case'u':
				return formatBaseX(value, 10, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
			case'i':
			case'd': {
				number = parseInt(+value);
				prefix = number < 0 ? '-' : positivePrefix;
				value = prefix + pad(String(Math.abs(number)), precision, '0', false);
				return justify(value, prefix, leftJustify, minWidth, zeroPad);
			}
			case'e':
			case'E':
			case'f':
			case'F':
			case'g':
			case'G': {
				number = +value;
				prefix = number < 0 ? '-' : positivePrefix;
				method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(type.toLowerCase())];
				textTransform = ['toString', 'toUpperCase']['eEfFgG'.indexOf(type) % 2];
				value = prefix + Math.abs(number)[method](precision);
				return justify(value, prefix, leftJustify, minWidth, zeroPad)[textTransform]();
			}
			default:
				return substring;
		}
	};
	return format.replace(regex, doFormat);
}

function str_repeat(input, multiplier) { // 645
	return new Array(multiplier + 1).join(input);
}

function str_replace(search, replace, subject) {
	let s = subject,
		ra = r instanceof Array, sa = s instanceof Array,
		f = [].concat(search),
		r = [].concat(replace),
		i = (s = [].concat(s)).length,
		j = 0;
	while (j = 0, i--) {
		if (s[i]) {
			while (s[i] = (s[i] + '').split(f[j]).join(ra ? r[j] || "" : r[0]), ++j in f) {
			}
			;
		}
	}
	return sa ? s : s[0];
}

function strlen(string) { // 676
	var str = string + '',
		i = 0, chr = '', lgth = 0;
	let getWholeChar = function (str, i) {
		let code = str.charCodeAt(i),
			next = '', prev = '';
		if (0xD800 <= code && code <= 0xDBFF) {
			if (str.length <= (i + 1)) {
				throw'High surrogate without following low surrogate';
			}
			next = str.charCodeAt(i + 1);
			if (0xDC00 > next || next > 0xDFFF) {
				throw'High surrogate without following low surrogate';
			}
			return str[i] + str[i + 1];
		} else if (0xDC00 <= code && code <= 0xDFFF) {
			if (i === 0) {
				throw'Low surrogate without preceding high surrogate';
			}
			prev = str.charCodeAt(i - 1);
			if (0xD800 > prev || prev > 0xDBFF) {
				throw'Low surrogate without preceding high surrogate';
			}
			return false;
		}
		return str[i];
	};
	for (i = 0, lgth = 0; i < str.length; i++) {
		if ((chr = getWholeChar(str, i)) === false) {
			continue;
		}
		lgth++;
	}
	return lgth;
}

function utf8_decode(str_data) { // 804
	let tmp_arr = [], i = 0, ac = 0, c1 = 0, c2 = 0, c3 = 0;
	str_data += '';
	while (i < str_data.length) {
		c1 = str_data.charCodeAt(i);
		if (c1 < 128) {
			tmp_arr[ac++] = String.fromCharCode(c1);
			i++;
		} else if ((c1 > 191) && (c1 < 224)) {
			c2 = str_data.charCodeAt(i + 1);
			tmp_arr[ac++] = String.fromCharCode(((c1 & 31) << 6) | (c2 & 63));
			i += 2;
		} else {
			c2 = str_data.charCodeAt(i + 1);
			c3 = str_data.charCodeAt(i + 2);
			tmp_arr[ac++] = String.fromCharCode(((c1 & 15) << 12) | ((c2 & 63) << 6) | (c3 & 63));
			i += 3;
		}
	}
	return tmp_arr.join('');
}
