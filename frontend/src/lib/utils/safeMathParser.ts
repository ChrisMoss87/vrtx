/**
 * Safe Math Parser
 *
 * A recursive descent parser for mathematical expressions that doesn't use eval() or new Function().
 * Supports: +, -, *, /, %, parentheses, and common math functions.
 */

type Token = {
	type: 'NUMBER' | 'OPERATOR' | 'FUNCTION' | 'LPAREN' | 'RPAREN' | 'COMMA' | 'STRING' | 'BOOLEAN';
	value: string | number | boolean;
};

/**
 * Tokenize an expression into tokens
 */
function tokenize(expression: string): Token[] {
	const tokens: Token[] = [];
	let i = 0;

	while (i < expression.length) {
		const char = expression[i];

		// Skip whitespace
		if (/\s/.test(char)) {
			i++;
			continue;
		}

		// Numbers (including decimals)
		if (/\d/.test(char) || (char === '.' && /\d/.test(expression[i + 1]))) {
			let num = '';
			while (i < expression.length && (/\d/.test(expression[i]) || expression[i] === '.')) {
				num += expression[i];
				i++;
			}
			tokens.push({ type: 'NUMBER', value: parseFloat(num) });
			continue;
		}

		// Operators
		if (['+', '-', '*', '/', '%'].includes(char)) {
			tokens.push({ type: 'OPERATOR', value: char });
			i++;
			continue;
		}

		// Comparison operators
		if (['<', '>', '=', '!'].includes(char)) {
			let op = char;
			if (expression[i + 1] === '=') {
				op += '=';
				i++;
			}
			if (char === '=' && expression[i + 1] === '=') {
				op = '==';
				i++;
			}
			tokens.push({ type: 'OPERATOR', value: op });
			i++;
			continue;
		}

		// Logical operators
		if (char === '&' && expression[i + 1] === '&') {
			tokens.push({ type: 'OPERATOR', value: '&&' });
			i += 2;
			continue;
		}
		if (char === '|' && expression[i + 1] === '|') {
			tokens.push({ type: 'OPERATOR', value: '||' });
			i += 2;
			continue;
		}

		// Parentheses
		if (char === '(') {
			tokens.push({ type: 'LPAREN', value: '(' });
			i++;
			continue;
		}
		if (char === ')') {
			tokens.push({ type: 'RPAREN', value: ')' });
			i++;
			continue;
		}

		// Comma (for function arguments)
		if (char === ',') {
			tokens.push({ type: 'COMMA', value: ',' });
			i++;
			continue;
		}

		// Strings (single or double quoted)
		if (char === '"' || char === "'") {
			const quote = char;
			let str = '';
			i++; // Skip opening quote
			while (i < expression.length && expression[i] !== quote) {
				if (expression[i] === '\\' && expression[i + 1] === quote) {
					str += quote;
					i += 2;
				} else {
					str += expression[i];
					i++;
				}
			}
			i++; // Skip closing quote
			tokens.push({ type: 'STRING', value: str });
			continue;
		}

		// Function names and boolean literals
		if (/[a-zA-Z_]/.test(char)) {
			let name = '';
			while (i < expression.length && /[a-zA-Z0-9_]/.test(expression[i])) {
				name += expression[i];
				i++;
			}

			// Check for boolean literals
			if (name.toLowerCase() === 'true') {
				tokens.push({ type: 'BOOLEAN', value: true });
			} else if (name.toLowerCase() === 'false') {
				tokens.push({ type: 'BOOLEAN', value: false });
			} else {
				tokens.push({ type: 'FUNCTION', value: name.toLowerCase() });
			}
			continue;
		}

		// Unknown character - skip
		i++;
	}

	return tokens;
}

/**
 * Parser class for recursive descent parsing
 */
class Parser {
	private tokens: Token[];
	private pos: number = 0;

	constructor(tokens: Token[]) {
		this.tokens = tokens;
	}

	private peek(): Token | null {
		return this.tokens[this.pos] || null;
	}

	private consume(): Token | null {
		return this.tokens[this.pos++] || null;
	}

	private expect(type: Token['type']): Token {
		const token = this.consume();
		if (!token || token.type !== type) {
			throw new Error(`Expected ${type}, got ${token?.type || 'end of input'}`);
		}
		return token;
	}

	// Parse logical OR (lowest precedence)
	parse(): number | string | boolean {
		return this.parseLogicalOr();
	}

	private parseLogicalOr(): number | string | boolean {
		let left = this.parseLogicalAnd();

		while (this.peek()?.type === 'OPERATOR' && this.peek()?.value === '||') {
			this.consume();
			const right = this.parseLogicalAnd();
			left = Boolean(left) || Boolean(right);
		}

		return left;
	}

	private parseLogicalAnd(): number | string | boolean {
		let left = this.parseComparison();

		while (this.peek()?.type === 'OPERATOR' && this.peek()?.value === '&&') {
			this.consume();
			const right = this.parseComparison();
			left = Boolean(left) && Boolean(right);
		}

		return left;
	}

	private parseComparison(): number | string | boolean {
		let left = this.parseAddSub();

		const compOps = ['==', '!=', '<', '>', '<=', '>=', '===', '!=='];
		while (this.peek()?.type === 'OPERATOR' && compOps.includes(String(this.peek()?.value))) {
			const op = this.consume()!.value as string;
			const right = this.parseAddSub();

			switch (op) {
				case '==':
				case '===':
					left = left === right;
					break;
				case '!=':
				case '!==':
					left = left !== right;
					break;
				case '<':
					left = Number(left) < Number(right);
					break;
				case '>':
					left = Number(left) > Number(right);
					break;
				case '<=':
					left = Number(left) <= Number(right);
					break;
				case '>=':
					left = Number(left) >= Number(right);
					break;
			}
		}

		return left;
	}

	private parseAddSub(): number | string | boolean {
		let left = this.parseMulDiv();

		while (
			this.peek()?.type === 'OPERATOR' &&
			(this.peek()?.value === '+' || this.peek()?.value === '-')
		) {
			const op = this.consume()!.value;
			const right = this.parseMulDiv();

			if (op === '+') {
				// String concatenation or numeric addition
				if (typeof left === 'string' || typeof right === 'string') {
					left = String(left) + String(right);
				} else {
					left = Number(left) + Number(right);
				}
			} else {
				left = Number(left) - Number(right);
			}
		}

		return left;
	}

	private parseMulDiv(): number | string | boolean {
		let left = this.parseUnary();

		while (
			this.peek()?.type === 'OPERATOR' &&
			['*', '/', '%'].includes(String(this.peek()?.value))
		) {
			const op = this.consume()!.value;
			const right = this.parseUnary();

			const leftNum = Number(left);
			const rightNum = Number(right);

			if (op === '*') {
				left = leftNum * rightNum;
			} else if (op === '/') {
				if (rightNum === 0) throw new Error('Division by zero');
				left = leftNum / rightNum;
			} else if (op === '%') {
				if (rightNum === 0) throw new Error('Modulo by zero');
				left = leftNum % rightNum;
			}
		}

		return left;
	}

	private parseUnary(): number | string | boolean {
		if (this.peek()?.type === 'OPERATOR' && this.peek()?.value === '-') {
			this.consume();
			return -Number(this.parsePrimary());
		}
		if (this.peek()?.type === 'OPERATOR' && this.peek()?.value === '+') {
			this.consume();
			return Number(this.parsePrimary());
		}
		return this.parsePrimary();
	}

	private parsePrimary(): number | string | boolean {
		const token = this.peek();

		if (!token) {
			throw new Error('Unexpected end of expression');
		}

		// Number
		if (token.type === 'NUMBER') {
			this.consume();
			return token.value as number;
		}

		// String
		if (token.type === 'STRING') {
			this.consume();
			return token.value as string;
		}

		// Boolean
		if (token.type === 'BOOLEAN') {
			this.consume();
			return token.value as boolean;
		}

		// Parenthesized expression
		if (token.type === 'LPAREN') {
			this.consume();
			const result = this.parse();
			this.expect('RPAREN');
			return result;
		}

		// Function call
		if (token.type === 'FUNCTION') {
			return this.parseFunction();
		}

		throw new Error(`Unexpected token: ${token.type}`);
	}

	private parseFunction(): number | string | boolean {
		const nameToken = this.consume()!;
		const funcName = nameToken.value as string;

		this.expect('LPAREN');

		const args: (number | string | boolean)[] = [];

		// Parse arguments
		if (this.peek()?.type !== 'RPAREN') {
			args.push(this.parse());

			while (this.peek()?.type === 'COMMA') {
				this.consume();
				args.push(this.parse());
			}
		}

		this.expect('RPAREN');

		return this.executeFunction(funcName, args);
	}

	private executeFunction(name: string, args: (number | string | boolean)[]): number | string | boolean {
		const numArgs = args.map((a) => (typeof a === 'number' ? a : Number(a)));

		switch (name) {
			// Math functions
			case 'sum':
				return numArgs.reduce((a, b) => a + b, 0);
			case 'average':
			case 'avg':
				return numArgs.length > 0 ? numArgs.reduce((a, b) => a + b, 0) / numArgs.length : 0;
			case 'min':
				return Math.min(...numArgs);
			case 'max':
				return Math.max(...numArgs);
			case 'round':
				const decimals = numArgs[1] || 0;
				const factor = Math.pow(10, decimals);
				return Math.round(numArgs[0] * factor) / factor;
			case 'ceil':
			case 'ceiling':
				return Math.ceil(numArgs[0]);
			case 'floor':
				return Math.floor(numArgs[0]);
			case 'abs':
				return Math.abs(numArgs[0]);
			case 'pow':
			case 'power':
				return Math.pow(numArgs[0], numArgs[1] || 1);
			case 'sqrt':
				return Math.sqrt(numArgs[0]);

			// Logical functions
			case 'if':
			case 'iffunc':
				return args[0] ? args[1] : args[2];
			case 'and':
			case 'andfunc':
				return args.every(Boolean);
			case 'or':
			case 'orfunc':
				return args.some(Boolean);
			case 'not':
				return !args[0];
			case 'isblank':
				return args[0] === null || args[0] === undefined || args[0] === '';
			case 'isnumber':
				return !isNaN(Number(args[0]));

			// Text functions
			case 'concat':
				return args.map((a) => String(a ?? '')).join('');
			case 'upper':
				return String(args[0] ?? '').toUpperCase();
			case 'lower':
				return String(args[0] ?? '').toLowerCase();
			case 'trim':
				return String(args[0] ?? '').trim();
			case 'left':
				return String(args[0] ?? '').substring(0, Number(args[1]) || 0);
			case 'right': {
				const str = String(args[0] ?? '');
				const len = Number(args[1]) || 0;
				return str.substring(str.length - len);
			}
			case 'substring':
			case 'substr':
				return String(args[0] ?? '').substring(
					Number(args[1]) || 0,
					(Number(args[1]) || 0) + (Number(args[2]) || 0)
				);
			case 'length':
			case 'len':
				return String(args[0] ?? '').length;

			default:
				throw new Error(`Unknown function: ${name}`);
		}
	}
}

/**
 * Safely evaluate a mathematical expression
 */
export function safeMathEvaluate(expression: string): number | string | boolean {
	try {
		const tokens = tokenize(expression);
		if (tokens.length === 0) {
			return 0;
		}
		const parser = new Parser(tokens);
		return parser.parse();
	} catch (error) {
		console.warn('Safe math evaluation failed:', expression, error);
		return 0;
	}
}

/**
 * Check if an expression is safe to evaluate
 */
export function isSafeExpression(expression: string): boolean {
	// Remove strings first (they can contain anything)
	const withoutStrings = expression.replace(/"[^"]*"|'[^']*'/g, '""');

	// Check for dangerous patterns
	const dangerousPatterns = [
		/\beval\b/i,
		/\bFunction\b/,
		/\bimport\b/i,
		/\brequire\b/i,
		/\bfetch\b/i,
		/\bwindow\b/i,
		/\bdocument\b/i,
		/\bglobal\b/i,
		/\bprocess\b/i,
		/\b__proto__\b/,
		/\bconstructor\b/,
		/\bprototype\b/
	];

	for (const pattern of dangerousPatterns) {
		if (pattern.test(withoutStrings)) {
			return false;
		}
	}

	return true;
}

export default safeMathEvaluate;
