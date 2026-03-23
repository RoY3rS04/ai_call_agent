package internal

import (
	"fmt"

	"github.com/redis/go-redis/v9"
)

type Address struct {
	Host string
	Port int
}

type RedisConfig struct {
	Addr     Address
	Password string
	DB       int
	Protocol int
}

func NewRedisClient(redisConfig RedisConfig) *redis.Client {

	return redis.NewClient(&redis.Options{
		Addr:     fmt.Sprintf("%s:%d", redisConfig.Addr.Host, redisConfig.Addr.Port),
		Password: redisConfig.Password,
		DB:       redisConfig.DB,
		Protocol: redisConfig.Protocol,
	})

}
