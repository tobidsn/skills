import { User } from '../models/User';
import { signJwt } from '../lib/jwt';

class AuthService {
  async verify({ email, password }: { email: string; password: string }) {
    const user = await User.findOne({ email });
    if (!user) throw new Error('invalid credentials');
    const ok = await user.comparePassword(password);
    if (!ok) throw new Error('invalid credentials');
    return { token: signJwt({ sub: user.id }) };
  }
}

export const authService = new AuthService();
